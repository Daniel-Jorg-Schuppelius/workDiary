<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WaitStepService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Models\ProcedureStepRun;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Serverseitige Warte-/Trockenschritte (Feature 047, MVP-064). Der früheste
 * Fortsetzungszeitpunkt (`wait_until`) wird persistiert und gegen die
 * Server-Zeit geprüft – Neuladen oder ein anderer Client kann die Blockade
 * nicht umgehen. Vorzeitige Fortsetzung ist nur als auditierte Abweichung
 * möglich (Status „Deviated" mit Begründung).
 */
class WaitStepService {
    /** Startet die Wartezeit eines Schritts und blockiert die Fortsetzung. */
    public function beginWait(ProcedureStepRun $step, int $seconds): ProcedureStepRun {
        $now = Carbon::now();
        $step->forceFill([
            'status' => ProcedureStepRunStatus::Blocked,
            'wait_started_at' => $now,
            'wait_until' => $now->copy()->addSeconds(max(0, $seconds)),
        ])->save();

        return $step;
    }

    /** Ist die Wartezeit serverseitig abgelaufen? */
    public function canContinue(ProcedureStepRun $step): bool {
        return $step->wait_until !== null && Carbon::now()->greaterThanOrEqualTo($step->wait_until);
    }

    /**
     * Setzt den Schritt fort. Vor Fristablauf nur als berechtigte Abweichung
     * (`$asDeviation = true`), sonst wird die Fortsetzung verweigert.
     */
    public function continueStep(ProcedureStepRun $step, bool $asDeviation = false, ?string $reason = null, ?int $userId = null): ProcedureStepRun {
        $elapsed = $this->canContinue($step);
        if (! $elapsed && ! $asDeviation) {
            throw new RuntimeException('Die Wartezeit ist noch nicht abgelaufen.');
        }

        $step->forceFill([
            'status' => $elapsed ? ProcedureStepRunStatus::Done : ProcedureStepRunStatus::Deviated,
            'executed_by_user_id' => $userId,
            'executed_at' => Carbon::now(),
            'note' => $elapsed ? $step->note : ($reason ?? 'Vorzeitige Fortsetzung der Wartezeit'),
        ])->save();

        return $step;
    }
}
