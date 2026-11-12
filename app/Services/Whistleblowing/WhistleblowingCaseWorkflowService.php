<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingCaseWorkflowService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Models\Platform\User;
use App\Models\Whistleblowing\{Portal, WhistleblowingCase};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Einzige Stelle fuer Statuswechsel eines Falls (Abschnitt 8). Controller setzen
 * Statusfelder NIE direkt. Prueft erlaubte Uebergaenge, fuehrt Seiteneffekte aus
 * (acknowledged_at, closed_at, retention_due_at, legal_hold_at) und schreibt ein
 * inhaltsfreies Status-Event. Begründungen werden als verschluesselte interne
 * Notiz abgelegt – NICHT in den Event-Metadaten (die bleiben content-frei).
 */
class WhistleblowingCaseWorkflowService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly WhistleblowingMessageService $messages,
    ) {}

    /** Eingangsbestaetigung (Abschnitt 7.3, Frist 7 Tage). */
    public function acknowledge(WhistleblowingCase $case, User $actor): void {
        $from = $this->status($case);
        if ($from !== CaseStatus::Submitted) {
            throw InvalidCaseTransition::between($from, CaseStatus::Acknowledged);
        }

        DB::transaction(function () use ($case, $actor): void {
            $case->forceFill([
                'status' => CaseStatus::Acknowledged->value,
                'acknowledged_at' => Carbon::now(),
            ])->save();

            $this->events->record($case, WhistleblowingEventService::CASE_ACKNOWLEDGED, $actor);
        });
    }

    /**
     * Generischer Statuswechsel. Abschluss und Wiedereröffnung verlangen eine
     * Begründung, die Löschsperre Rechtsgrundlage, Verantwortlichen und
     * Prüfdatum (Abschnitt 8); alles wird als interne Notiz verschluesselt abgelegt.
     *
     * @param  array{basis?: ?string, responsible?: ?string, review_at?: ?\Carbon\CarbonInterface}  $legalHold
     */
    public function transition(WhistleblowingCase $case, CaseStatus $to, ?User $actor = null, ?string $reason = null, array $legalHold = []): void {
        $from = $this->status($case);
        // Gelöscht wird nur über den WhistleblowingDeletionService: Ein bloßer
        // Statuswechsel ließe Inhalte und Schlüssel stehen und schriebe keinen Grabstein.
        if ($to === CaseStatus::Deleted) {
            throw InvalidCaseTransition::between($from, $to);
        }
        $this->assertAllowed($from, $to);

        if ($to->isClosed() && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('Ein Abschluss verlangt eine Begründung.');
        }

        $reopening = $from->isClosed() && $to === CaseStatus::Investigating;
        if ($reopening && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('Eine Wiedereröffnung verlangt eine Begründung.');
        }

        $basis = trim((string) ($legalHold['basis'] ?? ''));
        $responsible = trim((string) ($legalHold['responsible'] ?? ''));
        $reviewAt = $legalHold['review_at'] ?? null;
        if ($to === CaseStatus::LegalHold && ($basis === '' || $responsible === '' || $reviewAt === null)) {
            throw new InvalidArgumentException('Eine Löschsperre verlangt Rechtsgrundlage, Verantwortlichen und Prüfdatum.');
        }

        DB::transaction(function () use ($case, $from, $to, $actor, $reason, $reopening, $basis, $responsible, $reviewAt): void {
            $attributes = ['status' => $to->value];
            // Wer die Bestätigung überspringt (eingegangen → Prüfung), bestätigt den Eingang
            // damit: Das Postfach zeigt der meldenden Person ab hier „in Prüfung“.
            $acknowledges = $from === CaseStatus::Submitted && $case->getAttribute('acknowledged_at') === null;
            if ($acknowledges) {
                $attributes['acknowledged_at'] = Carbon::now();
            }

            if ($to->isClosed()) {
                $now = Carbon::now();
                $attributes['closed_at'] = $now;
                $attributes['retention_due_at'] = $now->copy()->addMonths($this->retentionMonths($case));
            }
            if ($to === CaseStatus::LegalHold) {
                $attributes['legal_hold_at'] = Carbon::now();
                $attributes['legal_hold_review_at'] = $reviewAt;
                $reason = implode("\n", array_filter([
                    __('Rechtsgrundlage') . ': ' . $basis,
                    __('Verantwortlich') . ': ' . $responsible,
                    __('Prüfung am') . ': ' . $reviewAt->format('Y-m-d'),
                    trim((string) $reason),
                ], static fn (string $line): bool => $line !== ''));
            }
            if ($from === CaseStatus::LegalHold) {
                $attributes['legal_hold_review_at'] = null;
            }
            if ($reopening) {
                // Die Aufbewahrungsfrist beginnt mit dem nächsten Abschluss neu.
                $attributes['closed_at'] = null;
                $attributes['retention_due_at'] = null;
            }

            $case->forceFill($attributes)->save();

            if ($reason !== null && trim($reason) !== '' && $actor !== null) {
                $this->messages->addInternalNote($case, $reason, $actor);
            }

            if ($acknowledges) {
                $this->events->record($case, WhistleblowingEventService::CASE_ACKNOWLEDGED, $actor);
            }

            $event = match (true) {
                $to === CaseStatus::LegalHold => WhistleblowingEventService::CASE_LEGAL_HOLD_SET,
                $reopening => WhistleblowingEventService::CASE_REOPENED,
                default => WhistleblowingEventService::CASE_STATUS_CHANGED,
            };

            $this->events->record($case, $event, $actor, [
                'from' => $from->value,
                'to' => $to->value,
            ]);
        });
    }

    /** Aufbewahrung des Portals der Organisation, sonst der Konfigurationswert. */
    private function retentionMonths(WhistleblowingCase $case): int {
        $months = Portal::withoutGlobalScopes()
            ->where('organization_id', $case->getAttribute('organization_id'))
            ->value('retention_months');

        return (int) ($months ?: config('whistleblowing.retention_months', 36));
    }

    private function assertAllowed(CaseStatus $from, CaseStatus $to): void {
        if (! $from->canTransitionTo($to)) {
            throw InvalidCaseTransition::between($from, $to);
        }
    }

    private function status(WhistleblowingCase $case): CaseStatus {
        $value = $case->getAttribute('status');

        return $value instanceof CaseStatus ? $value : CaseStatus::from((string) $value);
    }
}
