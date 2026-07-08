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
use App\Models\{AssetAssignment, CommunicationNote, Document, MaintenancePlan, OpenIssue, ServiceTicket, ShiftExchange, SlaContract, SlaContractQuota, User, UserQualification};
use App\Models\Isms\{IsmsCertificate, IsmsCorrectiveAction, IsmsRisk, IsmsRiskAssessment, IsmsSupplierAssessment, IsmsVulnerability};
use App\Services\Isms\ConformityService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\{SlaQuotaService, SlaTimer};
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Fristen-Scanner für Benachrichtigungen & Eskalationen (MVP-018).
 *
 * Findet fällige/überfällige Offene Punkte, Kommunikations-Folgeaktionen und
 * ablaufende Dokumente und feuert die zugehörigen NotificationEvents über den
 * zentralen Dispatcher. Idempotent: das notification_dispatch_log verhindert
 * pro (Organisation, Ereignis, Subjekt, Stufe) jeden Doppel-Versand.
 *
 * Läuft ohne Mandantenkontext (Konsolen-Prozess) und sieht damit alle
 * Organisationen; die Regel-Auflösung erfolgt pro Datensatz über dessen
 * organization_id.
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
        $sent += $this->scanSlaQuotas($dispatcher, app(SlaQuotaService::class));
        $sent += $this->scanAssetReturns($dispatcher);
        $sent += $this->scanMaintenance($dispatcher, $expiringDays);
        $sent += $this->scanQualificationExpiry($dispatcher, $expiringDays);
        $sent += $this->scanPendingShiftExchanges($dispatcher);

        $this->info(sprintf('%d Benachrichtigung(en) versendet.', $sent));

        return self::SUCCESS;
    }

    private function scanOpenIssues(NotificationDispatcher $dispatcher, int $dueDays): int {
        $openStates = array_map(
            static fn(OpenIssueStatus $s): string => $s->value,
            array_filter(OpenIssueStatus::cases(), static fn(OpenIssueStatus $s): bool => $s->isOpen()),
        );
        $now = Carbon::now();
        $sent = 0;

        // Fällig innerhalb des Vorlaufs.
        OpenIssue::query()
            ->whereIn('status', $openStates)
            ->whereNotNull('due_at')
            ->where('due_at', '>', $now)
            ->where('due_at', '<=', $now->copy()->addDays($dueDays))
            ->chunkById(200, function (Collection $issues) use ($dispatcher, &$sent): void {
                foreach ($issues as $issue) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::OpenIssueDueSoon,
                        $issue,
                        $this->issueAffected($issue),
                        $this->issuePayload($issue, 'due_soon'),
                        dedup: true,
                    );
                }
            });

        // Überfällig + Eskalation.
        OpenIssue::query()
            ->whereIn('status', $openStates)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->chunkById(200, function (Collection $issues) use ($dispatcher, &$sent): void {
                foreach ($issues as $issue) {
                    $payload = $this->issuePayload($issue, 'overdue');
                    $sent += $dispatcher->notify(
                        NotificationEvent::OpenIssueOverdue,
                        $issue,
                        $this->issueAffected($issue),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::OpenIssueOverdue, $issue, $payload);
                }
            });

        return $sent;
    }

    private function scanCommunicationFollowups(NotificationDispatcher $dispatcher, int $dueDays): int {
        $now = Carbon::now();
        $sent = 0;

        $pending = static fn() => CommunicationNote::query()
            ->whereNotNull('next_action_due_at')
            ->whereNull('next_action_completed_at');

        $pending()
            ->where('next_action_due_at', '>', $now)
            ->where('next_action_due_at', '<=', $now->copy()->addDays($dueDays))
            ->chunkById(200, function (Collection $notes) use ($dispatcher, &$sent): void {
                foreach ($notes as $note) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::CommunicationFollowupDueSoon,
                        $note,
                        $this->noteAffected($note),
                        $this->notePayload($note, 'followup_due_soon'),
                        dedup: true,
                    );
                }
            });

        $pending()
            ->where('next_action_due_at', '<=', $now)
            ->chunkById(200, function (Collection $notes) use ($dispatcher, &$sent): void {
                foreach ($notes as $note) {
                    $payload = $this->notePayload($note, 'followup_overdue');
                    $sent += $dispatcher->notify(
                        NotificationEvent::CommunicationFollowupOverdue,
                        $note,
                        $this->noteAffected($note),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::CommunicationFollowupOverdue, $note, $payload);
                }
            });

        return $sent;
    }

    private function scanDocuments(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $sent = 0;

        Document::query()
            ->expiringWithin($expiringDays)
            ->chunkById(200, function (Collection $documents) use ($dispatcher, &$sent): void {
                foreach ($documents as $document) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::DocumentExpiringSoon,
                        $document,
                        $this->documentAffected($document),
                        $this->documentPayload($document, 'expiring_soon'),
                        dedup: true,
                    );
                }
            });

        Document::query()
            ->expired()
            ->chunkById(200, function (Collection $documents) use ($dispatcher, &$sent): void {
                foreach ($documents as $document) {
                    $payload = $this->documentPayload($document, 'expired');
                    $sent += $dispatcher->notify(
                        NotificationEvent::DocumentExpired,
                        $document,
                        $this->documentAffected($document),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::DocumentExpired, $document, $payload);
                }
            });

        return $sent;
    }

    /**
     * ISMS-Zertifikate (Feature 046, Inkrement B): erst den automatischen
     * Verfall durchsetzen (certified ohne heute gültiges Zertifikat →
     * certificateExpired, ConformityService), dann ablaufende Zertifikate
     * melden (Vorlauf --expiring-days, Default 30 Tage; Empfänger gemäß
     * Default-Regel an die Rollen teamleitung/admin). Dedup über das
     * notification_dispatch_log pro Zertifikat.
     */
    private function scanIsmsCertificates(NotificationDispatcher $dispatcher, ConformityService $conformity, int $expiringDays): int {
        $expired = $conformity->expireOverdue();
        if ($expired > 0) {
            $this->info(sprintf('%d Konformitätsstatus auf „Zertifikat abgelaufen" gesetzt.', $expired));
        }

        $today = Carbon::today();
        $sent = 0;

        IsmsCertificate::query()
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
            ->chunkById(200, function (Collection $certificates) use ($dispatcher, &$sent): void {
                foreach ($certificates as $certificate) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::IsmsCertificateExpiring,
                        $certificate,
                        null,
                        $this->certificatePayload($certificate),
                        dedup: true,
                    );
                }
            });

        return $sent;
    }

    /**
     * ISMS-Korrekturmaßnahmen (Feature 046, Inkrement C): überfällige
     * Maßnahmen (due_on überschritten, Status open/inProgress) melden —
     * Empfänger ist der Maßnahmen-Verantwortliche (notify_affected),
     * Default-Fallback die Rolle teamleitung (NotificationEvent).
     * Dedup über das notification_dispatch_log pro Maßnahme; Eskalation
     * analog zu den übrigen Überfälligkeits-Ereignissen.
     */
    private function scanIsmsCorrectiveActions(NotificationDispatcher $dispatcher): int {
        $sent = 0;

        IsmsCorrectiveAction::query()
            ->overdue()
            ->chunkById(200, function (Collection $actions) use ($dispatcher, &$sent): void {
                foreach ($actions as $action) {
                    $payload = $this->correctiveActionPayload($action);
                    $sent += $dispatcher->notify(
                        NotificationEvent::IsmsCorrectiveActionOverdue,
                        $action,
                        $action->owner()->first(),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::IsmsCorrectiveActionOverdue, $action, $payload);
                }
            });

        return $sent;
    }

    /**
     * Risiko-Bewertungshistorie (Feature 046, Inkrement D): die JÜNGSTE
     * freigegebene Netto-Bewertung je Risiko ist der maßgebliche Stand —
     * liegt ihr valid_until (Ablauf-/Reviewdatum des akzeptierten
     * Restrisikos) innerhalb des Vorlaufs (--expiring-days, Default 30
     * Tage) oder ist es überschritten, geht isms.riskReviewDue an den
     * Risikoeigentümer (notify_affected), Default-Fallback Teamleitung.
     * Geschlossene Risiken werden übersprungen. Abgrenzung: das Feld
     * isms_risks.review_due_on wird NICHT gescannt (nur UI-Hinweis im
     * Register) — das Event feuert ausschließlich auf assessment.valid_until.
     * Dedup über das notification_dispatch_log pro Bewertungsstand.
     */
    private function scanIsmsRiskAssessments(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $today = Carbon::today();
        $sent = 0;

        // Nur der jüngste freigegebene Netto-Stand je Risiko zählt — ältere
        // (abgelöste) Stände dürfen kein Review mehr anstoßen.
        $latestApprovedNetIds = IsmsRiskAssessment::query()
            ->approvedNet()
            ->selectRaw('max(id)')
            ->groupBy('isms_risk_id');

        IsmsRiskAssessment::query()
            ->whereIn('id', $latestApprovedNetIds)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
            ->whereHas('risk', fn($query) => $query->where('status', '!=', RiskStatus::Closed->value))
            ->chunkById(200, function (Collection $assessments) use ($dispatcher, &$sent): void {
                foreach ($assessments as $assessment) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::IsmsRiskReviewDue,
                        $assessment,
                        $this->riskAssessmentAffected($assessment),
                        $this->riskAssessmentPayload($assessment),
                        dedup: true,
                    );
                }
            });

        return $sent;
    }

    /**
     * Schwachstellenregister (Feature 044, MVP 2): überfällige Schwachstellen
     * (due_on überschritten, Status open/underReview/mitigating) melden —
     * Empfänger ist der Schwachstellen-Verantwortliche (notify_affected),
     * Default-Fallback die Rolle teamleitung (NotificationEvent). Dedup über
     * das notification_dispatch_log pro Schwachstelle; Eskalation analog zu den
     * übrigen Überfälligkeits-Ereignissen.
     */
    private function scanIsmsVulnerabilities(NotificationDispatcher $dispatcher): int {
        $sent = 0;

        IsmsVulnerability::query()
            ->overdue()
            ->chunkById(200, function (Collection $vulnerabilities) use ($dispatcher, &$sent): void {
                foreach ($vulnerabilities as $vulnerability) {
                    $payload = $this->vulnerabilityPayload($vulnerability);
                    $sent += $dispatcher->notify(
                        NotificationEvent::IsmsVulnerabilityOverdue,
                        $vulnerability,
                        $vulnerability->owner()->first(),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::IsmsVulnerabilityOverdue, $vulnerability, $payload);
                }
            });

        return $sent;
    }

    /**
     * Lieferantenbewertung (Feature 044, MVP 2/3): überfällige Lieferanten-
     * Reviews (next_review_on überschritten, Status nicht „approved") melden —
     * Empfänger ist der Bewertungs-Verantwortliche (notify_affected),
     * Default-Fallback die Rolle teamleitung (NotificationEvent). Dedup über
     * das notification_dispatch_log pro Bewertung; Eskalation analog zu den
     * übrigen Überfälligkeits-Ereignissen.
     */
    private function scanIsmsSupplierReviews(NotificationDispatcher $dispatcher): int {
        $sent = 0;

        IsmsSupplierAssessment::query()
            ->reviewOverdue()
            ->chunkById(200, function (Collection $assessments) use ($dispatcher, &$sent): void {
                foreach ($assessments as $assessment) {
                    $payload = $this->supplierReviewPayload($assessment);
                    $sent += $dispatcher->notify(
                        NotificationEvent::IsmsSupplierReviewOverdue,
                        $assessment,
                        $assessment->owner()->first(),
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::IsmsSupplierReviewOverdue, $assessment, $payload);
                }
            });

        return $sent;
    }

    /**
     * SLA-Eskalation (Feature 010): offene Service-Tickets mit gefährdeter
     * (Restzeit unter dem Schwellwert, SlaTimer::AT_RISK_FRACTION) bzw.
     * überschrittener Lösungsfrist melden. Empfänger ist der Ticket-
     * Verantwortliche (notify_affected), Default-Fallback/Eskalationskette die
     * Rolle teamleitung (NotificationEvent). Maßgeblich ist die Lösungsfrist
     * (resolution_due_at). Dedup über das notification_dispatch_log pro Ticket;
     * verletzte Tickets eskalieren zusätzlich (supportsEscalation).
     */
    /**
     * Wiedervorlagen (Feature 065, P3): wartende Tickets mit überschrittener
     * wait_until-Frist → Notification an den Wiedervorlage-Verantwortlichen
     * (wait_owner, Fallback Bearbeiter). Dedup über das Dispatch-Log.
     */
    private function scanWaitingTickets(NotificationDispatcher $dispatcher): int {
        $sent = 0;

        ServiceTicket::query()
            ->whereIn('status', [
                \App\Enums\ServiceTicket\ServiceTicketStatus::WaitingCustomer->value,
                \App\Enums\ServiceTicket\ServiceTicketStatus::WaitingExternal->value,
                \App\Enums\ServiceTicket\ServiceTicketStatus::Paused->value,
            ])
            ->whereNotNull('wait_until')
            ->where('wait_until', '<=', Carbon::now())
            ->chunkById(200, function (Collection $tickets) use ($dispatcher, &$sent): void {
                /** @var Collection<int, ServiceTicket> $tickets */
                foreach ($tickets as $ticket) {
                    $owner = $ticket->wait_owner_id !== null
                        ? \App\Models\User::query()->find($ticket->wait_owner_id)
                        : $ticket->assignedTo;
                    $sent += $dispatcher->notify(
                        NotificationEvent::TicketWaitingExpired,
                        $ticket,
                        $owner,
                        [
                            'title' => (string) __('Wiedervorlage fällig: Ticket :no', ['no' => $ticket->ticket_no]),
                            'body' => (string) ($ticket->wait_reason ?? $ticket->title),
                            'url' => route('service-tickets.show', $ticket),
                        ],
                        dedup: true,
                    );
                }
            });

        return $sent;
    }

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
     * Ausgabe-/Rückgabe-Workflow (Feature 009): offene Asset-Zuweisungen
     * (returned_at = null) mit überschrittener erwarteter Rückgabe melden.
     * Empfänger ist die ausleihende Person (notify_affected), Default-Fallback/
     * Eskalationskette die Rolle teamleitung (NotificationEvent). Dedup über das
     * notification_dispatch_log pro Zuweisung.
     */
    private function scanAssetReturns(NotificationDispatcher $dispatcher): int {
        $now = Carbon::now();
        $sent = 0;

        AssetAssignment::query()
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<=', $now)
            ->with(['asset:id,name,asset_no', 'assignedToUser'])
            ->chunkById(200, function (Collection $assignments) use ($dispatcher, &$sent): void {
                /** @var Collection<int, AssetAssignment> $assignments */
                foreach ($assignments as $assignment) {
                    $payload = $this->assetReturnPayload($assignment);
                    $sent += $dispatcher->notify(
                        NotificationEvent::AssetReturnOverdue,
                        $assignment,
                        $assignment->assignedToUser,
                        $payload,
                        dedup: true,
                    );
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::AssetReturnOverdue, $assignment, $payload);
                }
            });

        return $sent;
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
        $sent = 0;

        UserQualification::query()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
            ->with(['user', 'qualification'])
            ->chunkById(200, function (Collection $assignments) use ($dispatcher, &$sent): void {
                /** @var Collection<int, UserQualification> $assignments */
                foreach ($assignments as $assignment) {
                    $user = $assignment->user;
                    if ($user === null) {
                        continue;
                    }
                    $sent += $dispatcher->notify(
                        NotificationEvent::QualificationExpiring,
                        $assignment,
                        $user,
                        $this->qualificationPayload($assignment),
                        dedup: true,
                    );
                }
            });

        return $sent;
    }

    /**
     * Schichttausch (Feature 007): noch offene Tausch-Anträge (requested/accepted)
     * erinnern die Teamleitung an die ausstehende Freigabe. Dedup über das
     * notification_dispatch_log pro Antrag (1× pro Tag genügt; das Re-Notify
     * greift erst, wenn der Antrag entschieden und ein neuer angelegt wird).
     */
    private function scanPendingShiftExchanges(NotificationDispatcher $dispatcher): int {
        $sent = 0;

        ShiftExchange::query()
            ->whereIn('status', [
                ShiftExchangeStatus::Requested->value,
                ShiftExchangeStatus::Accepted->value,
            ])
            ->with(['scheduledShift', 'targetUser'])
            ->chunkById(200, function (Collection $exchanges) use ($dispatcher, &$sent): void {
                /** @var Collection<int, ShiftExchange> $exchanges */
                foreach ($exchanges as $exchange) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::ShiftExchangeRequested,
                        $exchange,
                        $exchange->targetUser,
                        $this->shiftExchangePayload($exchange),
                        dedup: true,
                    );
                }
            });

        return $sent;
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function shiftExchangePayload(ShiftExchange $exchange): array {
        return [
            'title' => (string) __('schedule.exchange.notification_request_title'),
            'message' => (string) __('schedule.exchange.notification_pending_message', [
                'date' => $exchange->scheduledShift?->date?->format('d.m.Y') ?? '–',
            ]),
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

    /** @return array{title: string, message: string, url: string|null} */
    private function qualificationPayload(UserQualification $assignment): array {
        $name = (string) ($assignment->qualification->name ?? '');

        return [
            'title' => $name,
            'message' => (string) __('notification.message.qualification_expiring', [
                'date' => $assignment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('reports.qualifications'),
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function assetReturnPayload(AssetAssignment $assignment): array {
        $asset = $assignment->asset;
        $title = $asset !== null ? trim($asset->asset_no . ' — ' . $asset->name, ' —') : '';

        return [
            'title' => $title,
            'message' => (string) __('notification.message.asset_return_overdue', [
                'date' => $assignment->expected_return_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'url' => $asset !== null ? route('assets.show', $asset) : null,
        ];
    }

    // ── Betroffene & Payloads ──────────────────────────────────────────────

    private function issueAffected(OpenIssue $issue): ?User {
        return $issue->assignee ?? $issue->creator;
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function issuePayload(OpenIssue $issue, string $messageKey): array {
        return [
            'title' => (string) $issue->title,
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $issue->due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'url' => \App\Support\NotificationLinks::openIssueUrl($issue),
        ];
    }

    private function noteAffected(CommunicationNote $note): ?User {
        return $note->getAttribute('next_action_user_id') !== null
            ? User::query()->find((int) $note->getAttribute('next_action_user_id'))
            : User::query()->find((int) $note->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function notePayload(CommunicationNote $note, string $messageKey): array {
        return [
            'title' => (string) ($note->getAttribute('next_action') ?: $note->getAttribute('subject') ?: __('notification.message.followup_fallback_title')),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $note->next_action_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'url' => null,
        ];
    }

    private function documentAffected(Document $document): ?User {
        return User::query()->find((int) $document->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function documentPayload(Document $document, string $messageKey): array {
        return [
            'title' => (string) $document->getAttribute('title'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $document->getAttribute('valid_until')?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('documents.index'),
        ];
    }

    /**
     * Wartungs-/Prüfpläne (Feature 009): fällig innerhalb des Vorlaufs →
     * dueSoon; überschrittene Fälligkeit → overdue + Eskalationsstufe an den
     * Org-Admin. Betrifft keine Einzelperson (an die Teamleitung).
     */
    private function scanMaintenance(NotificationDispatcher $dispatcher, int $expiringDays): int {
        $sent = 0;
        $today = Carbon::now()->toDateString();
        $soon = Carbon::now()->addDays($expiringDays)->toDateString();

        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_on')
            ->whereBetween('next_due_on', [$today, $soon])
            ->chunkById(200, function (Collection $plans) use ($dispatcher, &$sent): void {
                foreach ($plans as $plan) {
                    $sent += $dispatcher->notify(
                        NotificationEvent::MaintenanceDueSoon,
                        $plan,
                        null,
                        $this->maintenancePayload($plan, 'maintenance_due_soon'),
                        dedup: true,
                    );
                }
            });

        MaintenancePlan::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_on')
            ->where('next_due_on', '<', $today)
            ->chunkById(200, function (Collection $plans) use ($dispatcher, &$sent): void {
                foreach ($plans as $plan) {
                    $payload = $this->maintenancePayload($plan, 'maintenance_overdue');
                    $sent += $dispatcher->notify(NotificationEvent::MaintenanceOverdue, $plan, null, $payload, dedup: true);
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::MaintenanceOverdue, $plan, $payload);
                }
            });

        return $sent;
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function maintenancePayload(MaintenancePlan $plan, string $messageKey): array {
        return [
            'title' => (string) $plan->label,
            'message' => (string) __('notification.message.' . $messageKey, [
                'label' => (string) $plan->label,
                'date' => $plan->next_due_on?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('assets.index'),
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function certificatePayload(IsmsCertificate $certificate): array {
        $normStatus = $certificate->normStatus()->withTrashed()->first();

        return [
            'title' => trim(($normStatus?->normLabel() ?? '') . ' — ' . $certificate->certificate_no, ' —'),
            'message' => (string) __('notification.message.certificate_expiring', [
                'date' => $certificate->valid_until->format('d.m.Y'),
            ]),
            'url' => route('isms.conformity.index'),
        ];
    }

    private function riskAssessmentAffected(IsmsRiskAssessment $assessment): ?User {
        return $assessment->risk()->withTrashed()->first()?->owner()->first();
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function riskAssessmentPayload(IsmsRiskAssessment $assessment): array {
        /** @var IsmsRisk|null $risk */
        $risk = $assessment->risk()->withTrashed()->first();

        return [
            'title' => trim(($risk?->displayNo() ?? '') . ' — ' . (string) $risk?->title, ' —'),
            'message' => (string) __('notification.message.risk_review_due', [
                'date' => $assessment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('isms.risks.index'),
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function vulnerabilityPayload(IsmsVulnerability $vulnerability): array {
        return [
            'title' => trim($vulnerability->displayNo() . ' — ' . $vulnerability->title, ' —'),
            'message' => (string) __('notification.message.vulnerability_overdue', [
                'date' => $vulnerability->due_on?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('isms.vulnerabilities.index'),
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function supplierReviewPayload(IsmsSupplierAssessment $assessment): array {
        return [
            'title' => trim($assessment->displayNo() . ' — ' . $assessment->displayName(), ' —'),
            'message' => (string) __('notification.message.supplier_review_overdue', [
                'date' => $assessment->next_review_on?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('isms.suppliers.index'),
        ];
    }

    /**
     * SLA-Inklusivzeit-Kontingente (Feature 010 → Rang 44): erreicht der
     * Verbrauch im aktuellen Zeitraum die Warnschwelle, geht einmal je Periode
     * eine Benachrichtigung an die Teamleitung. Dedup pro Periode über
     * `last_warned_period` am Kontingent (die Dispatcher-Dedup ist subjektbasiert
     * und würde eine neue Periode sonst nicht erneut melden).
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
            'url' => null,
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function slaPayload(ServiceTicket $ticket, string $messageKey): array {
        return [
            'title' => trim($ticket->ticket_no . ' — ' . $ticket->title, ' —'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $ticket->resolution_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'url' => route('service-tickets.show', $ticket),
        ];
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function correctiveActionPayload(IsmsCorrectiveAction $action): array {
        $finding = $action->finding()->withTrashed()->first();

        return [
            'title' => trim(($finding?->displayNo() ?? '') . ' — ' . $action->title, ' —'),
            'message' => (string) __('notification.message.corrective_action_overdue', [
                'date' => $action->due_on?->format('d.m.Y') ?? '–',
            ]),
            'url' => route('isms.audits.index'),
        ];
    }
}
