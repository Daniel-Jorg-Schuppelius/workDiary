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
use App\Models\Whistleblowing\WhistleblowingCase;
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
     * Generischer Statuswechsel. Abschluss-Status (closed_*) verlangen eine
     * Begründung; diese wird als interne Notiz verschluesselt abgelegt.
     */
    public function transition(WhistleblowingCase $case, CaseStatus $to, ?User $actor = null, ?string $reason = null): void {
        $from = $this->status($case);
        $this->assertAllowed($from, $to);

        if ($to->isClosed() && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('Ein Abschluss verlangt eine Begründung.');
        }

        DB::transaction(function () use ($case, $from, $to, $actor, $reason): void {
            $attributes = ['status' => $to->value];

            if ($to->isClosed()) {
                $now = Carbon::now();
                $attributes['closed_at'] = $now;
                $attributes['retention_due_at'] = $now->copy()->addMonths(
                    (int) config('whistleblowing.retention_months', 36)
                );
            }
            if ($to === CaseStatus::LegalHold) {
                $attributes['legal_hold_at'] = Carbon::now();
            }

            $case->forceFill($attributes)->save();

            if ($reason !== null && trim($reason) !== '' && $actor !== null) {
                $this->messages->addInternalNote($case, $reason, $actor);
            }

            $event = $to === CaseStatus::LegalHold
                ? WhistleblowingEventService::CASE_LEGAL_HOLD_SET
                : WhistleblowingEventService::CASE_STATUS_CHANGED;

            $this->events->record($case, $event, $actor, [
                'from' => $from->value,
                'to' => $to->value,
            ]);
        });
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
