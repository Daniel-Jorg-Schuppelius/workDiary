<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanDeadlinesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Notification;

use App\Enums\Isms\RiskStatus;
use App\Enums\Notification\NotificationEvent;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\Shift\ShiftExchangeStatus;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{AssetAssignment, CommunicationNote, Document, MaintenancePlan, OpenIssue, Problem, ServiceTicket, ShiftExchange, SlaContract, SlaContractQuota, User, UserQualification};
use App\Models\Isms\{IsmsCertificate, IsmsCorrectiveAction, IsmsRisk, IsmsRiskAssessment, IsmsSupplierAssessment, IsmsVulnerability};
use App\Services\Isms\ConformityService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\{SlaQuotaService, SlaTimer};
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Support\Carbon;

/**
 * Fristen-Scanner für Benachrichtigungen & Eskalationen (MVP-018): findet
 * fällige/überfällige Sätze und feuert NotificationEvents über den zentralen
 * Dispatcher. Idempotent über das notification_dispatch_log. Payloads tragen
 * `due_at` (MVP-331) für den Kalender-Kanal. Läuft ohne Mandantenkontext →
 * sieht alle Organisationen; Regel-Auflösung pro Datensatz über organization_id.
 *
 * C18: die strukturgleichen Scans laufen deskriptor-getrieben über
 * {@see self::runScan()} (Phase 'due' → notify(dedup), Phase 'overdue' →
 * notify(dedup)+escalateIfDue); delegierende Scans über
 * {@see self::sumPerOrganization()}. Nur SLA-Tickets (Statusweiche je Zeile)
 * und SLA-Kontingente (Perioden-Dedup + Statefortschreibung) bleiben explizit.
 *
 * @phpstan-type TNotifyPayload array{title: string, message?: string|null, url?: string|null, icon?: string|null, due_at?: \DateTimeInterface|string|null}
 */
class ScanDeadlinesCommand extends Command {
    protected $signature = 'notifications:scan-deadlines
        {--due-days=3 : Vorlauf in Tagen für dueSoon-Ereignisse}
        {--expiring-days=30 : Vorlauf in Tagen für ablaufende Dokumente}';

    protected $description = 'Scannt Fristen (Offene Punkte, Folgeaktionen, Dokumente) und versendet Benachrichtigungen inkl. Eskalation.';

    public function handle(NotificationDispatcher $dispatcher, ConformityService $conformity): int {
        $dueDays = max(1, (int) $this->option('due-days'));
        $expiringDays = max(1, (int) $this->option('expiring-days'));

        $sent = 0;
        $sent += $this->scanOpenIssues($dispatcher, $dueDays);
        $sent += $this->scanCommunicationFollowups($dispatcher, $dueDays);
        $sent += $this->scanDocuments($dispatcher, $expiringDays);
        $sent += $this->scanIsmsCertificates($dispatcher, $conformity, $expiringDays);
        $sent += $this->scanIsmsCorrectiveActions($dispatcher);
        $sent += $this->scanIsmsRiskAssessments($dispatcher, $expiringDays);
        $sent += $this->scanIsmsVulnerabilities($dispatcher);
        $sent += $this->scanIsmsSupplierReviews($dispatcher);
        $sent += $this->scanSlaTickets($dispatcher, app(SlaTimer::class));
        $sent += $this->scanWaitingTickets($dispatcher);
        $sent += $this->scanProblemEffectiveness($dispatcher);
        $sent += $this->scanSlaQuotas($dispatcher, app(SlaQuotaService::class));
        $sent += $this->scanAssetReturns($dispatcher);
        $sent += $this->scanTenderDeadlines($dispatcher, $dueDays);
        $sent += $this->scanMaintenance($dispatcher, $expiringDays);
        $sent += $this->scanQualificationExpiry($dispatcher, $expiringDays);
        $sent += $this->scanPendingShiftExchanges($dispatcher);
        $sent += $this->scanRentalReturns();
        $sent += $this->scanAssetFinanceDeadlines();
        $sent += $this->scanContractObligations();
        $sent += $this->scanAssetInspections();
        $sent += $this->scanDriverLicenseChecks($dispatcher, $expiringDays);
        $sent += $this->scanDomainExpiry($dispatcher, $expiringDays);
        $sent += $this->scanInvestmentDecisions($dispatcher, $dueDays);

        $this->info(sprintf('%d Benachrichtigung(en) versendet.', $sent));

        return self::SUCCESS;
    }

    /**
     * Eskalationskette (Vollaudit 2026-07, N19): Schritte des SLA-Vertrags
     * (after_minutes/notify) gegen die Überschreitung der Lösungsfrist prüfen;
     * `escalation_level` schreitet fort und dedupliziert dadurch selbst.
     * `notify`: numerische User-ID oder Rollen-Slug (erster aktiver Nutzer
     * der Rolle in der Organisation, deterministisch nach ID).
     */
    private function advanceEscalationChain(NotificationDispatcher $dispatcher, ServiceTicket $ticket, Carbon $now): int {
        $contract = $ticket->slaContract;
        $chain = $contract !== null ? array_values((array) $contract->escalation_chain) : [];
        if ($chain === [] || $ticket->resolution_due_at === null) {
            return 0;
        }

        $overdueMinutes = (int) $ticket->resolution_due_at->diffInMinutes($now, false);
        if ($overdueMinutes <= 0) {
            return 0;
        }

        $sent = 0;
        $level = (int) $ticket->escalation_level;
        foreach ($chain as $index => $step) {
            $stepNo = $index + 1;
            if ($stepNo <= $level) {
                continue;
            }
            if ($overdueMinutes < (int) $step['after_minutes']) {
                break;
            }

            $recipient = $this->resolveEscalationRecipient($ticket, (string) $step['notify']);
            $payload = $this->slaPayload($ticket, 'sla_breached');
            $payload['message'] = (string) __('Eskalationsstufe :level erreicht (:minutes Min. über Lösungsfrist).', [
                'level' => $stepNo,
                'minutes' => $overdueMinutes,
            ]);
            $sent += $dispatcher->notify(NotificationEvent::SlaBreached, $ticket, $recipient, $payload);

            $ticket->forceFill(['escalation_level' => $stepNo])->save();
            $level = $stepNo;
        }

        return $sent;
    }

    private function resolveEscalationRecipient(ServiceTicket $ticket, string $notify): ?\App\Models\User {
        if ($notify === '') {
            return $ticket->assignedTo;
        }
        if (ctype_digit($notify)) {
            return \App\Models\User::query()->withoutGlobalScopes()
                ->where('organization_id', $ticket->organization_id)
                ->find((int) $notify);
        }

        return \App\Models\User::query()->withoutGlobalScopes()
            ->where('organization_id', $ticket->organization_id)
            ->whereNull('deactivated_at')
            ->role($notify)
            ->orderBy('id')
            ->first();
    }

    /**
     * Vollaudit 2026-07 (H12): ablaufende Domains bzw. fehlgeschlagene
     * Verlängerungen (failure_at) an die Admins — Feature 083 hatte keinerlei
     * Benachrichtigungen.
     */
    private function scanDomainExpiry(NotificationDispatcher $dispatcher, int $expiringDays): int {
        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => \App\Models\Domain\DomainProjection::query()
                    ->withoutGlobalScopes()
                    ->where(fn($q) => $q
                        ->whereNotNull('failure_at')
                        ->orWhere(fn($qq) => $qq
                            ->whereNotNull('expiration_at')
                            ->whereBetween('expiration_at', [now(), now()->addDays($expiringDays)]))),
                'event' => NotificationEvent::DomainExpiring,
                'payload' => fn(\App\Models\Domain\DomainProjection $domain): array => [
                    'title' => (string) __('notification.message.domain_expiring_title', ['domain' => (string) $domain->external_domain]),
                    'title_key' => 'notification.message.domain_expiring_title',
                    'title_params' => ['domain' => (string) $domain->external_domain],
                    'message' => $domain->failure_at !== null
                        ? (string) __('Verlängerung fehlgeschlagen am :date.', ['date' => $domain->failure_at->format('d.m.Y')])
                        : (string) __('Läuft ab am :date.', ['date' => $domain->expiration_at?->format('d.m.Y') ?? '—']),
                    'url' => route('domains.show', $domain),
                    'due_at' => $domain->expiration_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Vollaudit 2026-07 (M31): Budget-Anträge, die länger als :dueDays Tage
     * in Freigabe hängen — Fristenschiene MVP-209.
     */
    private function scanInvestmentDecisions(NotificationDispatcher $dispatcher, int $dueDays): int {
        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => \App\Models\Investments\InvestmentBudgetRequest::query()
                    ->withoutGlobalScopes()
                    ->where('status', 'in_approval')
                    ->where('created_at', '<=', now()->subDays($dueDays)),
                'event' => NotificationEvent::InvestmentDecisionDue,
                'payload' => function (\App\Models\Investments\InvestmentBudgetRequest $request): array {
                    $case = $request->investmentCase()->withoutGlobalScopes()->firstOrFail();

                    return [
                        'title' => (string) __('notification.message.investment_decision_due_title', ['title' => (string) $case->title]),
                        'title_key' => 'notification.message.investment_decision_due_title',
                        'title_params' => ['title' => (string) $case->title],
                        'message' => null,
                        'url' => route('investments.show', $case),
                    ];
                },
            ],
        ]);
    }

    // ── Generische Skelette (C18) ──────────────────────────────────────────

    /**
     * Generische Fristen-Schleife: je Phase lazyById(200) über die Query;
     * Phase 'due' → notify(dedup: true), Phase 'overdue' → notify(dedup: true)
     * + escalateIfDue. `require_affected` überspringt Zeilen ohne auflösbaren
     * Empfänger (heutiges continue-Verhalten einzelner Scans).
     *
     * @template TModel of Model
     *
     * @param array{
     *     affected?: Closure(TModel): ?User,
     *     require_affected?: bool,
     *     due?: array{query: Closure(): \Illuminate\Database\Eloquent\Builder<TModel>, event: NotificationEvent, payload: Closure(TModel): TNotifyPayload},
     *     overdue?: array{query: Closure(): \Illuminate\Database\Eloquent\Builder<TModel>, event: NotificationEvent, payload: Closure(TModel): TNotifyPayload},
     * } $scan
     */
    private function runScan(NotificationDispatcher $dispatcher, array $scan): int {
        $affected = $scan['affected'] ?? static fn(Model $row): ?User => null;
        $requireAffected = (bool) ($scan['require_affected'] ?? false);
        $sent = 0;

        foreach (['due' => false, 'overdue' => true] as $phase => $escalate) {
            if (! isset($scan[$phase])) {
                continue;
            }

            ['query' => $query, 'event' => $event, 'payload' => $payload] = $scan[$phase];

            foreach ($query()->lazyById(200) as $row) {
                $user = $affected($row);
                if ($requireAffected && $user === null) {
                    continue;
                }

                $data = $payload($row);
                $sent += $dispatcher->notify($event, $row, $user, $data, dedup: true);
                if ($escalate) {
                    $sent += $dispatcher->escalateIfDue($event, $row, $data);
                }
            }
        }

        return $sent;
    }

    /**
     * Org-Refetch-Skelett der delegierenden Scans: distinct organization_id aus
     * der (ungescopten) Query, Organisation laden, Handler je Organisation —
     * Nummernkreis-/Audit-Kontext liegt im jeweiligen Fach-Service.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant Model> $query
     * @param Closure(\App\Models\Organization): int $handler
     */
    private function sumPerOrganization(\Illuminate\Database\Eloquent\Builder $query, Closure $handler): int {
        $sent = 0;

        foreach ($query->distinct()->pluck('organization_id') as $organizationId) {
            $organization = \App\Models\Organization::query()->whereKey($organizationId)->first();
            if ($organization !== null) {
                $sent += $handler($organization);
            }
        }

        return $sent;
    }

    // ── Deskriptor-getriebene Scans ────────────────────────────────────────

    private function scanOpenIssues(NotificationDispatcher $dispatcher, int $dueDays): int {
        $openStates = OpenIssueStatus::openValues();
        $now = Carbon::now();

        return $this->runScan($dispatcher, [
            'affected' => fn(OpenIssue $issue): ?User => $this->issueAffected($issue),
            'due' => [
                'query' => fn() => OpenIssue::query()
                    ->whereIn('status', $openStates)
                    ->whereNotNull('due_at')
                    ->where('due_at', '>', $now)
                    ->where('due_at', '<=', $now->copy()->addDays($dueDays)),
                'event' => NotificationEvent::OpenIssueDueSoon,
                'payload' => fn(OpenIssue $issue): array => $this->issuePayload($issue, 'due_soon'),
            ],
            'overdue' => [
                'query' => fn() => OpenIssue::query()
                    ->whereIn('status', $openStates)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', $now),
                'event' => NotificationEvent::OpenIssueOverdue,
                'payload' => fn(OpenIssue $issue): array => $this->issuePayload($issue, 'overdue'),
            ],
        ]);
    }

    private function scanCommunicationFollowups(NotificationDispatcher $dispatcher, int $dueDays): int {
        $now = Carbon::now();
        $pending = static fn() => CommunicationNote::query()
            ->whereNotNull('next_action_due_at')
            ->whereNull('next_action_completed_at');

        return $this->runScan($dispatcher, [
            'affected' => fn(CommunicationNote $note): ?User => $this->noteAffected($note),
            'due' => [
                'query' => fn() => $pending()
                    ->where('next_action_due_at', '>', $now)
                    ->where('next_action_due_at', '<=', $now->copy()->addDays($dueDays)),
                'event' => NotificationEvent::CommunicationFollowupDueSoon,
                'payload' => fn(CommunicationNote $note): array => $this->notePayload($note, 'followup_due_soon'),
            ],
            'overdue' => [
                'query' => fn() => $pending()->where('next_action_due_at', '<=', $now),
                'event' => NotificationEvent::CommunicationFollowupOverdue,
                'payload' => fn(CommunicationNote $note): array => $this->notePayload($note, 'followup_overdue'),
            ],
        ]);
    }

    private function scanDocuments(NotificationDispatcher $dispatcher, int $expiringDays): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(Document $document): ?User => $this->documentAffected($document),
            'due' => [
                'query' => fn() => Document::query()->expiringWithin($expiringDays),
                'event' => NotificationEvent::DocumentExpiringSoon,
                'payload' => fn(Document $document): array => $this->documentPayload($document, 'expiring_soon'),
            ],
            'overdue' => [
                'query' => fn() => Document::query()->expired(),
                'event' => NotificationEvent::DocumentExpired,
                'payload' => fn(Document $document): array => $this->documentPayload($document, 'expired'),
            ],
        ]);
    }

    /**
     * ISMS-Zertifikate (Feature 046, Inkrement B): erst den automatischen
     * Verfall durchsetzen (ConformityService), dann ablaufende Zertifikate
     * melden (Vorlauf --expiring-days).
     */
    private function scanIsmsCertificates(NotificationDispatcher $dispatcher, ConformityService $conformity, int $expiringDays): int {
        $expired = $conformity->expireOverdue();
        if ($expired > 0) {
            $this->info(sprintf('%d Konformitätsstatus auf „Zertifikat abgelaufen" gesetzt.', $expired));
        }

        $today = Carbon::today();

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => IsmsCertificate::query()
                    ->whereDate('valid_from', '<=', $today)
                    ->whereDate('valid_until', '>=', $today)
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays)),
                'event' => NotificationEvent::IsmsCertificateExpiring,
                'payload' => fn(IsmsCertificate $certificate): array => $this->certificatePayload($certificate),
            ],
        ]);
    }

    /**
     * ISMS-Korrekturmaßnahmen (Feature 046, Inkrement C): überfällige Maßnahmen
     * melden (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanIsmsCorrectiveActions(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsCorrectiveAction $action): ?User => $action->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsCorrectiveAction::query()->overdue(),
                'event' => NotificationEvent::IsmsCorrectiveActionOverdue,
                'payload' => fn(IsmsCorrectiveAction $action): array => $this->correctiveActionPayload($action),
            ],
        ]);
    }

    /**
     * Risiko-Bewertungshistorie (Feature 046, Inkrement D): jüngste freigegebene
     * Netto-Bewertung je Risiko; feuert isms.riskReviewDue bei nahem/überschrittenem
     * valid_until an den Risikoeigentümer (offene Risiken).
     * Abgrenzung: isms_risks.review_due_on wird NICHT gescannt — nur assessment.valid_until.
     */
    private function scanIsmsRiskAssessments(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::today();

        // Nur der jüngste freigegebene Netto-Stand je Risiko — ältere (abgelöste) dürfen kein Review anstoßen.
        $latestApprovedNetIds = IsmsRiskAssessment::query()
            ->approvedNet()
            ->selectRaw('max(id)')
            ->groupBy('isms_risk_id');

        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsRiskAssessment $assessment): ?User => $this->riskAssessmentAffected($assessment),
            'due' => [
                'query' => fn() => IsmsRiskAssessment::query()
                    ->whereIn('id', $latestApprovedNetIds)
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
                    ->whereHas('risk', fn($query) => $query->where('status', '!=', RiskStatus::Closed->value)),
                'event' => NotificationEvent::IsmsRiskReviewDue,
                'payload' => fn(IsmsRiskAssessment $assessment): array => $this->riskAssessmentPayload($assessment),
            ],
        ]);
    }

    /**
     * Schwachstellenregister (Feature 044, MVP 2): überfällige Schwachstellen
     * melden (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanIsmsVulnerabilities(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsVulnerability $vulnerability): ?User => $vulnerability->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsVulnerability::query()->overdue(),
                'event' => NotificationEvent::IsmsVulnerabilityOverdue,
                'payload' => fn(IsmsVulnerability $vulnerability): array => $this->vulnerabilityPayload($vulnerability),
            ],
        ]);
    }

    /**
     * Lieferantenbewertung (Feature 044, MVP 2/3): überfällige Reviews melden
     * (Empfänger Verantwortlicher, Fallback teamleitung) + Eskalation.
     */
    private function scanIsmsSupplierReviews(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(IsmsSupplierAssessment $assessment): ?User => $assessment->owner()->first(),
            'overdue' => [
                'query' => fn() => IsmsSupplierAssessment::query()->reviewOverdue(),
                'event' => NotificationEvent::IsmsSupplierReviewOverdue,
                'payload' => fn(IsmsSupplierAssessment $assessment): array => $this->supplierReviewPayload($assessment),
            ],
        ]);
    }

    /**
     * Wiedervorlagen (Feature 065, P3): wartende Tickets mit überschrittener
     * wait_until → Notification an wait_owner (Fallback Bearbeiter).
     */
    private function scanWaitingTickets(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(ServiceTicket $ticket): ?User => $ticket->wait_owner_id !== null
                ? User::query()->find($ticket->wait_owner_id)
                : $ticket->assignedTo,
            'due' => [
                'query' => fn() => ServiceTicket::query()
                    ->whereIn('status', [
                        ServiceTicketStatus::WaitingCustomer->value,
                        ServiceTicketStatus::WaitingExternal->value,
                        ServiceTicketStatus::Paused->value,
                    ])
                    ->whereNotNull('wait_until')
                    ->where('wait_until', '<=', Carbon::now()),
                'event' => NotificationEvent::TicketWaitingExpired,
                'payload' => fn(ServiceTicket $ticket): array => [
                    'title' => (string) __('Wiedervorlage fällig: Ticket :no', ['no' => $ticket->ticket_no]),
                    'title_key' => 'Wiedervorlage fällig: Ticket :no',
                    'title_params' => ['no' => $ticket->ticket_no],
                    'body' => (string) ($ticket->wait_reason ?? $ticket->title),
                    'url' => route('service-tickets.show', $ticket),
                    'due_at' => $ticket->wait_until,
                ],
            ],
        ]);
    }

    /**
     * Problem-Management (Feature 065, MVP-156): gelöste/Known-Error-Probleme
     * mit überschrittener Wirksamkeitsfrist ohne Prüfung melden (Empfänger
     * Owner, Fallback teamleitung) + Eskalation.
     */
    private function scanProblemEffectiveness(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(Problem $problem): ?User => $problem->owner()->first(),
            'overdue' => [
                'query' => fn() => Problem::query()
                    ->whereIn('status', ['resolved', 'known_error'])
                    ->whereNotNull('effectiveness_check_due_at')
                    ->where('effectiveness_check_due_at', '<=', Carbon::now())
                    ->whereNull('effectiveness_checked_at'),
                'event' => NotificationEvent::ProblemEffectivenessDue,
                'payload' => fn(Problem $problem): array => $this->problemEffectivenessPayload($problem),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function problemEffectivenessPayload(Problem $problem): array {
        return [
            'title' => (string) $problem->title,
            'message' => (string) __('Wirksamkeitsprüfung fällig seit :date.', [
                'date' => $problem->effectiveness_check_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'Wirksamkeitsprüfung fällig seit :date.',
            'message_params' => ['date' => $problem->effectiveness_check_due_at?->toIso8601String() ?? '–'],
            'url' => route('servicedesk.problems.show', $problem),
            'due_at' => $problem->effectiveness_check_due_at,
        ];
    }

    /**
     * SLA-Eskalation (Feature 010): offene Tickets mit gefährdeter/überschrittener
     * Lösungsfrist (resolution_due_at) melden; verletzte eskalieren zusätzlich.
     * Bleibt explizit (C18): die Statusweiche AtRisk/Breached läuft je Zeile über
     * den SlaTimer und passt nicht ins Zwei-Phasen-Skelett.
     */
    private function scanSlaTickets(NotificationDispatcher $dispatcher, SlaTimer $timer): int {
        $now = Carbon::now();
        $sent = 0;

        ServiceTicket::query()
            ->whereNotIn('status', [
                ServiceTicketStatus::Closed->value,
                ServiceTicketStatus::Rejected->value,
            ])
            ->whereNotNull('resolution_due_at')
            ->whereNull('resolved_at')
            ->chunkById(200, function (Collection $tickets) use ($dispatcher, $timer, $now, &$sent): void {
                /** @var Collection<int, ServiceTicket> $tickets */
                foreach ($tickets as $ticket) {
                    $status = $timer->resolutionStatus($ticket, $now);
                    if ($status === \App\Enums\ServiceTicket\SlaStatus::Breached) {
                        $payload = $this->slaPayload($ticket, 'sla_breached');
                        $sent += $dispatcher->notify(
                            NotificationEvent::SlaBreached,
                            $ticket,
                            $ticket->assignedTo,
                            $payload,
                            dedup: true,
                        );
                        $sent += $dispatcher->escalateIfDue(NotificationEvent::SlaBreached, $ticket, $payload);
                        // Vollaudit 2026-07 (N19): konfigurierte Eskalationskette
                        // des SLA-Vertrags abarbeiten (escalation_level schreitet fort).
                        $sent += $this->advanceEscalationChain($dispatcher, $ticket, $now);
                    } elseif ($status === \App\Enums\ServiceTicket\SlaStatus::AtRisk) {
                        $sent += $dispatcher->notify(
                            NotificationEvent::SlaAtRisk,
                            $ticket,
                            $ticket->assignedTo,
                            $this->slaPayload($ticket, 'sla_at_risk'),
                            dedup: true,
                        );
                    }
                }
            });

        return $sent;
    }

    /**
     * Ausgabe-/Rückgabe-Workflow (Feature 009): offene Asset-Zuweisungen mit
     * überschrittener erwarteter Rückgabe melden (Empfänger Ausleiher) + Eskalation.
     */
    private function scanAssetReturns(NotificationDispatcher $dispatcher): int {
        $now = Carbon::now();

        return $this->runScan($dispatcher, [
            'affected' => fn(AssetAssignment $assignment): ?User => $assignment->assignedToUser,
            'overdue' => [
                'query' => fn() => AssetAssignment::query()
                    ->whereNull('returned_at')
                    ->whereNotNull('expected_return_at')
                    ->where('expected_return_at', '<=', $now)
                    ->with(['asset:id,name,asset_no', 'assignedToUser']),
                'event' => NotificationEvent::AssetReturnOverdue,
                'payload' => fn(AssetAssignment $assignment): array => $this->assetReturnPayload($assignment),
            ],
        ]);
    }

    /**
     * Überfällige Verleih-Rückgaben (Feature 073, MVP-264): Statuswechsel
     * auf overdue + idempotente Benachrichtigung/Eskalation laufen im
     * RentalCaseService je Organisation (Nummernkreis-/Audit-Kontext).
     */
    private function scanRentalReturns(): int {
        $service = app(\App\Services\Rental\RentalCaseService::class);

        return $this->sumPerOrganization(
            \App\Models\Rental\RentalCase::query()
                ->withoutGlobalScopes()
                ->whereIn('status', [
                    \App\Enums\Rental\RentalCaseStatus::HandedOver->value,
                    \App\Enums\Rental\RentalCaseStatus::Overdue->value,
                ]),
            fn(\App\Models\Organization $organization): int => $service->escalateOverdue($organization),
        );
    }

    /**
     * Leasing-/Vertragsfristen (Feature 074, MVP-273/278): Warnung ab
     * Vorwarnzeit + Eskalation; Logik im AssetFinanceService je Organisation.
     */
    private function scanAssetFinanceDeadlines(): int {
        $service = app(\App\Services\AssetFinance\AssetFinanceService::class);

        return $this->sumPerOrganization(
            \App\Models\AssetFinance\AssetFinanceDeadline::query()
                ->withoutGlobalScopes()
                ->where('status', 'open'),
            fn(\App\Models\Organization $organization): int => $service->scanDeadlines($organization),
        );
    }

    /**
     * Allgemeine Vertragsobligationen (Welle D, CLM): Warnung ab Vorwarnzeit
     * + Eskalation; abgelaufene Obligationen laufender Verträge → missed.
     * Logik im ContractService je Organisation. Payload trägt due_at → der
     * Kalender-Kanal (A11) publiziert den Termin automatisch.
     */
    private function scanContractObligations(): int {
        $service = app(\App\Services\Contract\ContractService::class);

        return $this->sumPerOrganization(
            \App\Models\Contract\ContractObligation::query()
                ->withoutGlobalScopes()
                ->where('status', 'open'),
            fn(\App\Models\Organization $organization): int => $service->scanObligations($organization),
        );
    }

    /**
     * Prüfpflichten (Feature 075, MVP-285/288): Warnung ab Vorwarnzeit,
     * Einsatzsperren gemäß blocking_mode über das gemeinsame Modell (D12);
     * Logik im AssetComplianceService je Organisation.
     */
    private function scanAssetInspections(): int {
        $service = app(\App\Services\AssetCompliance\AssetComplianceService::class);

        return $this->sumPerOrganization(
            \App\Models\AssetCompliance\AssetComplianceAssignment::query()
                ->withoutGlobalScopes()
                ->where('is_active', true),
            fn(\App\Models\Organization $organization): int => $service->scanAssignments($organization),
        );
    }

    /**
     * Führerscheinkontrolle (MVP-417): jüngste Kontrolle je Fahrer mit
     * Fälligkeit innerhalb des Vorlaufs (--expiring-days) oder überfällig.
     * Empfänger: der Fahrer selbst (notify_affected) plus Teamleitung
     * (Fuhrparkverantwortung). Dedup pro Kontrolle über das
     * notification_dispatch_log; überfällige Kontrollen sperren zusätzlich
     * die Fahrzeugreservierung (VehicleReservationService-Guard).
     */
    private function scanDriverLicenseChecks(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($expiringDays);

        // Jüngste Kontrolle je Fahrer (ältere Zeilen sind Historie).
        $latestIds = \App\Models\DriverLicenseCheck::query()
            ->withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        return $this->runScan($dispatcher, [
            'affected' => fn(\App\Models\DriverLicenseCheck $check): ?User => $check->user,
            'require_affected' => true,
            'due' => [
                'query' => fn() => \App\Models\DriverLicenseCheck::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $latestIds)
                    ->whereDate('next_due_on', '<=', $horizon->toDateString())
                    ->with('user:id,name,organization_id')
                    ->orderBy('id'),
                'event' => NotificationEvent::DriverLicenseCheckDue,
                'payload' => function (\App\Models\DriverLicenseCheck $check) use ($today): array {
                    $name = (string) $check->user?->name;
                    $overdue = $today->greaterThan($check->next_due_on);

                    return [
                        'title' => $overdue
                            ? (string) __('Führerscheinkontrolle überfällig: :name', ['name' => $name])
                            : (string) __('Führerscheinkontrolle fällig: :name', ['name' => $name]),
                        'title_key' => $overdue
                            ? 'Führerscheinkontrolle überfällig: :name'
                            : 'Führerscheinkontrolle fällig: :name',
                        'title_params' => ['name' => $name],
                        'url' => route('driver-license-checks.index'),
                        'due_at' => $check->next_due_on,
                    ];
                },
            ],
        ]);
    }

    /**
     * Qualifikations-/Unterweisungsablauf (Feature 013): Mitarbeiter-
     * Qualifikationen mit gesetztem valid_until innerhalb des Vorlaufs
     * (--expiring-days, Default 30 Tage) melden. Empfänger ist die betroffene
     * Person (notify_affected), Default-Fallback die Rolle teamleitung
     * (NotificationEvent). Org-Kontext wird über den User aufgelöst. Dedup über
     * das notification_dispatch_log pro Pivot-Zeile (User × Qualifikation).
     */
    private function scanQualificationExpiry(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::today();

        return $this->runScan($dispatcher, [
            'affected' => fn(UserQualification $assignment): ?User => $assignment->user,
            'require_affected' => true,
            'due' => [
                'query' => fn() => UserQualification::query()
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '>=', $today)
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
                    ->with(['user', 'qualification']),
                'event' => NotificationEvent::QualificationExpiring,
                'payload' => fn(UserQualification $assignment): array => $this->qualificationPayload($assignment),
            ],
        ]);
    }

    /**
     * Schichttausch (Feature 007): noch offene Tausch-Anträge (requested/accepted)
     * erinnern die Teamleitung an die ausstehende Freigabe. Dedup über das
     * notification_dispatch_log pro Antrag (1× pro Tag genügt; das Re-Notify
     * greift erst, wenn der Antrag entschieden und ein neuer angelegt wird).
     */
    private function scanPendingShiftExchanges(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(ShiftExchange $exchange): ?User => $exchange->targetUser,
            'due' => [
                'query' => fn() => ShiftExchange::query()
                    ->whereIn('status', [
                        ShiftExchangeStatus::Requested->value,
                        ShiftExchangeStatus::Accepted->value,
                    ])
                    ->with(['scheduledShift', 'targetUser']),
                'event' => NotificationEvent::ShiftExchangeRequested,
                'payload' => fn(ShiftExchange $exchange): array => $this->shiftExchangePayload($exchange),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function shiftExchangePayload(ShiftExchange $exchange): array {
        return [
            'title' => (string) __('schedule.exchange.notification_request_title'),
            'title_key' => 'schedule.exchange.notification_request_title',
            'message' => (string) __('schedule.exchange.notification_pending_message', [
                'date' => $exchange->scheduledShift?->date?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'schedule.exchange.notification_pending_message',
            'message_params' => ['date' => $exchange->scheduledShift?->date?->toDateString() ?? '–'],
            'url' => $this->safeRoute('schedule.exchanges.index'),
        ];
    }

    private function safeRoute(string $name): ?string {
        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function qualificationPayload(UserQualification $assignment): array {
        $name = (string) ($assignment->qualification->name ?? '');

        return [
            'title' => $name,
            'message' => (string) __('notification.message.qualification_expiring', [
                'date' => $assignment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.qualification_expiring',
            'message_params' => ['date' => $assignment->valid_until?->toDateString() ?? '–'],
            'url' => route('reports.qualifications'),
            'due_at' => $assignment->valid_until,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    /**
     * Vergabefristen (MVP-626). Anders als die meisten Fristen sind sie
     * **Ausschlussfristen**: Wer die Angebotsfrist verstreichen lässt, ist raus
     * — eine Erinnerung danach hilft nicht mehr, wird aber trotzdem gemeldet,
     * damit die Akte geschlossen wird.
     *
     * Die Bindefrist läuft umgekehrt: Nach ihr ist der **Bieter** frei, das
     * Angebot also nicht mehr verbindlich.
     */
    private function scanTenderDeadlines(NotificationDispatcher $dispatcher, int $dueDays): int {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($dueDays);

        /** @var Closure(): \Illuminate\Database\Eloquent\Builder<ApplicationOpportunity> $open */
        $open = static fn (): \Illuminate\Database\Eloquent\Builder => ApplicationOpportunity::query()
            ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
            ->with('responsible');

        $sent = $this->runScan($dispatcher, [
            'affected' => fn (ApplicationOpportunity $tender): ?User => $tender->responsible,
            'due' => [
                'query' => fn () => $open()
                    ->whereNotNull('submission_deadline')
                    ->whereBetween('submission_deadline', [$today, $horizon]),
                'event' => NotificationEvent::TenderSubmissionDueSoon,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_submission_due_soon',
                    $tender->submission_deadline,
                ),
            ],
            'overdue' => [
                'query' => fn () => $open()
                    ->whereNotNull('submission_deadline')
                    ->where('submission_deadline', '<', $today),
                'event' => NotificationEvent::TenderSubmissionOverdue,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_submission_overdue',
                    $tender->submission_deadline,
                ),
            ],
        ]);

        // Die Bindefrist braucht einen eigenen Lauf: runScan kennt nur die
        // Phasen „fällig" und „überfällig", und eine ablaufende Bindefrist ist
        // weder das eine noch das andere - sie betrifft ein bereits
        // abgegebenes Angebot.
        $sent += $this->runScan($dispatcher, [
            'affected' => fn (ApplicationOpportunity $tender): ?User => $tender->responsible,
            'due' => [
                'query' => fn () => $open()
                    ->whereNotNull('binding_until')
                    ->whereBetween('binding_until', [$today, $horizon]),
                'event' => NotificationEvent::TenderBindingExpiring,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_binding_expiring',
                    $tender->binding_until,
                ),
            ],
        ]);

        return $sent;
    }

    /**
     * @return TNotifyPayload
     */
    private function tenderPayload(ApplicationOpportunity $tender, string $key, ?\Carbon\CarbonInterface $date): array {
        return [
            'title' => (string) $tender->title,
            'message' => (string) __('notification.message.' . $key, ['date' => $date?->format('d.m.Y') ?? '–']),
            'message_key' => 'notification.message.' . $key,
            'message_params' => ['date' => $date?->toDateString() ?? '–'],
            'url' => route('tenders.show', $tender),
            'due_at' => $date,
        ];
    }

    /**
     * @return TNotifyPayload
     */
    private function assetReturnPayload(AssetAssignment $assignment): array {
        $asset = $assignment->asset;
        $title = $asset !== null ? trim($asset->asset_no . ' — ' . $asset->name, ' —') : '';

        return [
            'title' => $title,
            'message' => (string) __('notification.message.asset_return_overdue', [
                'date' => $assignment->expected_return_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.asset_return_overdue',
            'message_params' => ['date' => $assignment->expected_return_at?->toIso8601String() ?? '–'],
            'url' => $asset !== null ? route('assets.show', $asset) : null,
            'due_at' => $assignment->expected_return_at,
        ];
    }

    // ── Betroffene & Payloads ──────────────────────────────────────────────

    private function issueAffected(OpenIssue $issue): ?User {
        return $issue->assignee ?? $issue->creator;
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function issuePayload(OpenIssue $issue, string $messageKey): array {
        return [
            'title' => (string) $issue->title,
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $issue->due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $issue->due_at?->toIso8601String() ?? '–'],
            'url' => \App\Support\NotificationLinks::openIssueUrl($issue),
            'due_at' => $issue->due_at,
        ];
    }

    private function noteAffected(CommunicationNote $note): ?User {
        return $note->getAttribute('next_action_user_id') !== null
            ? User::query()->find((int) $note->getAttribute('next_action_user_id'))
            : User::query()->find((int) $note->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function notePayload(CommunicationNote $note, string $messageKey): array {
        return [
            'title' => (string) ($note->getAttribute('next_action') ?: $note->getAttribute('subject') ?: __('notification.message.followup_fallback_title')),
            'title_key' => ($note->getAttribute('next_action') ?: $note->getAttribute('subject')) ? null : 'notification.message.followup_fallback_title',
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $note->next_action_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $note->next_action_due_at?->toIso8601String() ?? '–'],
            'url' => null,
            'due_at' => $note->next_action_due_at,
        ];
    }

    private function documentAffected(Document $document): ?User {
        return User::query()->find((int) $document->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function documentPayload(Document $document, string $messageKey): array {
        $validUntil = $document->getAttribute('valid_until');

        return [
            'title' => (string) $document->getAttribute('title'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $validUntil?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $validUntil instanceof \Illuminate\Support\Carbon ? $validUntil->toDateString() : '–'],
            'url' => route('documents.index'),
            'due_at' => $validUntil instanceof \Illuminate\Support\Carbon ? $validUntil : null,
        ];
    }

    /**
     * Wartungs-/Prüfpläne (Feature 009, MVP-336): fällig innerhalb des
     * Vorlaufs → dueSoon; überschrittene, unerledigte Fälligkeit → overdue
     * mit Eskalationskette (escalateIfDue — Stufe 1 an die Eskalationsrolle,
     * optionale Stufen 2/3 gemäß Regel, MVP-331). Empfänger ist der Asset-
     * Verantwortliche (aktueller Ausgabe-Inhaber, notify_affected),
     * Default-Fallback/Mitwisser die Rolle teamleitung (NotificationEvent).
     * Dedup über das notification_dispatch_log pro Plan und Stufe.
     */
    private function scanMaintenance(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::now()->toDateString();
        $soon = Carbon::now()->addDays($expiringDays)->toDateString();

        return $this->runScan($dispatcher, [
            'affected' => fn(MaintenancePlan $plan): ?User => $this->maintenanceAffected($plan),
            'due' => [
                'query' => fn() => MaintenancePlan::query()
                    ->where('is_active', true)
                    ->whereNotNull('next_due_on')
                    ->whereBetween('next_due_on', [$today, $soon])
                    ->with('asset.currentAssignment.assignedToUser'),
                'event' => NotificationEvent::MaintenanceDueSoon,
                'payload' => fn(MaintenancePlan $plan): array => $this->maintenancePayload($plan, 'maintenance_due_soon'),
            ],
            'overdue' => [
                'query' => fn() => MaintenancePlan::query()
                    ->where('is_active', true)
                    ->whereNotNull('next_due_on')
                    ->where('next_due_on', '<', $today)
                    ->with('asset.currentAssignment.assignedToUser'),
                'event' => NotificationEvent::MaintenanceOverdue,
                'payload' => fn(MaintenancePlan $plan): array => $this->maintenancePayload($plan, 'maintenance_overdue'),
            ],
        ]);
    }

    /**
     * Asset-Verantwortlicher eines Wartungsplans (MVP-336): der aktuelle
     * Ausgabe-Inhaber des verknüpften Assets (offene Zuweisung), sofern
     * vorhanden — sonst greift der Rollen-Fallback der Regel (teamleitung).
     */
    private function maintenanceAffected(MaintenancePlan $plan): ?User {
        return $plan->asset?->currentAssignment?->assignedToUser;
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function maintenancePayload(MaintenancePlan $plan, string $messageKey): array {
        return [
            'title' => (string) $plan->label,
            'message' => (string) __('notification.message.' . $messageKey, [
                'label' => (string) $plan->label,
                'date' => $plan->next_due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => [
                'label' => (string) $plan->label,
                'date' => $plan->next_due_on?->toDateString() ?? '–',
            ],
            'url' => route('assets.index'),
            'due_at' => $plan->next_due_on,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function certificatePayload(IsmsCertificate $certificate): array {
        $normStatus = $certificate->normStatus()->withTrashed()->first();

        return [
            'title' => trim(($normStatus?->normLabel() ?? '') . ' — ' . $certificate->certificate_no, ' —'),
            'message' => (string) __('notification.message.certificate_expiring', [
                'date' => $certificate->valid_until->format('d.m.Y'),
            ]),
            'message_key' => 'notification.message.certificate_expiring',
            'message_params' => ['date' => $certificate->valid_until->toDateString()],
            'url' => route('isms.conformity.index'),
            'due_at' => $certificate->valid_until,
        ];
    }

    private function riskAssessmentAffected(IsmsRiskAssessment $assessment): ?User {
        return $assessment->risk()->withTrashed()->first()?->owner()->first();
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function riskAssessmentPayload(IsmsRiskAssessment $assessment): array {
        /** @var IsmsRisk|null $risk */
        $risk = $assessment->risk()->withTrashed()->first();

        return [
            'title' => trim(($risk?->displayNo() ?? '') . ' — ' . (string) $risk?->title, ' —'),
            'message' => (string) __('notification.message.risk_review_due', [
                'date' => $assessment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.risk_review_due',
            'message_params' => ['date' => $assessment->valid_until?->toDateString() ?? '–'],
            'url' => route('isms.risks.index'),
            'due_at' => $assessment->valid_until,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function vulnerabilityPayload(IsmsVulnerability $vulnerability): array {
        return [
            'title' => trim($vulnerability->displayNo() . ' — ' . $vulnerability->title, ' —'),
            'message' => (string) __('notification.message.vulnerability_overdue', [
                'date' => $vulnerability->due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.vulnerability_overdue',
            'message_params' => ['date' => $vulnerability->due_on?->toDateString() ?? '–'],
            'url' => route('isms.vulnerabilities.index'),
            'due_at' => $vulnerability->due_on,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function supplierReviewPayload(IsmsSupplierAssessment $assessment): array {
        return [
            'title' => trim($assessment->displayNo() . ' — ' . $assessment->displayName(), ' —'),
            'message' => (string) __('notification.message.supplier_review_overdue', [
                'date' => $assessment->next_review_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.supplier_review_overdue',
            'message_params' => ['date' => $assessment->next_review_on?->toDateString() ?? '–'],
            'url' => route('isms.suppliers.index'),
            'due_at' => $assessment->next_review_on,
        ];
    }

    /**
     * SLA-Inklusivzeit-Kontingente (Feature 010 → Rang 44): erreicht der
     * Verbrauch im aktuellen Zeitraum die Warnschwelle, geht einmal je Periode
     * eine Benachrichtigung an die Teamleitung. Dedup pro Periode über
     * `last_warned_period` am Kontingent (die Dispatcher-Dedup ist subjektbasiert
     * und würde eine neue Periode sonst nicht erneut melden). Bleibt explizit
     * (C18): kein dedup-Flag, dafür Statefortschreibung je Zeile.
     */
    private function scanSlaQuotas(NotificationDispatcher $dispatcher, SlaQuotaService $quotas): int {
        $now = Carbon::now();
        $sent = 0;

        SlaContractQuota::query()->withoutGlobalScopes()
            ->chunkById(200, function (Collection $rows) use ($dispatcher, $quotas, $now, &$sent): void {
                /** @var Collection<int, SlaContractQuota> $rows */
                foreach ($rows as $quota) {
                    $contract = SlaContract::query()->withoutGlobalScopes()->find($quota->sla_contract_id);
                    if (! $contract instanceof SlaContract || ! $contract->is_active) {
                        continue;
                    }

                    $usage = $quotas->usage($contract, $quota, $now);
                    if (! $usage['threshold_reached'] || $quota->last_warned_period === $usage['period_key']) {
                        continue; // Schwelle nicht erreicht oder in dieser Periode bereits gewarnt
                    }

                    $sent += $dispatcher->notify(
                        NotificationEvent::SlaQuotaWarning,
                        $quota,
                        null,
                        $this->quotaPayload($contract, $usage),
                    );
                    $quota->forceFill(['last_warned_period' => $usage['period_key']])->save();
                }
            });

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array{title: string, message: string, url: null}
     */
    private function quotaPayload(SlaContract $contract, array $usage): array {
        return [
            'title' => trim($contract->code . ' — ' . $contract->label, ' —'),
            'message' => (string) __('notification.message.sla_quota_warning', [
                'percent' => (int) $usage['percentage'],
                'consumed' => (int) $usage['consumed_minutes'],
                'included' => (int) $usage['included_minutes'],
                'period' => (string) $usage['period_key'],
            ]),
            'message_key' => 'notification.message.sla_quota_warning',
            'message_params' => [
                'percent' => (int) $usage['percentage'],
                'consumed' => (int) $usage['consumed_minutes'],
                'included' => (int) $usage['included_minutes'],
                'period' => (string) $usage['period_key'],
            ],
            'url' => null,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function slaPayload(ServiceTicket $ticket, string $messageKey): array {
        return [
            'title' => trim($ticket->ticket_no . ' — ' . $ticket->title, ' —'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $ticket->resolution_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $ticket->resolution_due_at?->toIso8601String() ?? '–'],
            'url' => route('service-tickets.show', $ticket),
            'due_at' => $ticket->resolution_due_at,
        ];
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function correctiveActionPayload(IsmsCorrectiveAction $action): array {
        $finding = $action->finding()->withTrashed()->first();

        return [
            'title' => trim(($finding?->displayNo() ?? '') . ' — ' . $action->title, ' —'),
            'message' => (string) __('notification.message.corrective_action_overdue', [
                'date' => $action->due_on?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.corrective_action_overdue',
            'message_params' => ['date' => $action->due_on?->toDateString() ?? '–'],
            'url' => route('isms.audits.index'),
            'due_at' => $action->due_on,
        ];
    }
}
