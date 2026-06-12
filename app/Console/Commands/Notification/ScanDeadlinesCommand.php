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
use App\Models\{CommunicationNote, Document, OpenIssue, User};
use App\Models\Isms\{IsmsCertificate, IsmsCorrectiveAction, IsmsRisk, IsmsRiskAssessment};
use App\Services\Isms\ConformityService;
use App\Services\Notification\NotificationDispatcher;
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
