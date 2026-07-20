<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordsProcedureRunEvents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure\Concerns;

use App\Enums\Procedure\ProcedureRunEventType;
use App\Models\{ProcedureRun, ProcedureRunEvent, ProcedureStepRun, User};

/**
 * Zentraler Schreiber für ProcedureRunEvents (Vollaudit 2026-07, N38) —
 * ersetzt vier wortgleiche recordEvent()-Kopien. Die zwei Fehlersemantiken
 * bleiben bewusst getrennt: recordStepEvent() ignoriert Step-Runs ohne
 * verknüpften Run still (SecondPersonGate/BackupProofService), während
 * Aufrufer mit Pflicht-Run (ProcedureExecutionService) selbst werfen und
 * direkt recordRunEvent() nutzen.
 */
trait RecordsProcedureRunEvents {
    /** @param array<string, mixed>|null $payload */
    protected function recordRunEvent(ProcedureRun $run, ProcedureRunEventType $type, ?User $actor, ?ProcedureStepRun $stepRun = null, ?array $payload = null): ProcedureRunEvent {
        return ProcedureRunEvent::query()->create([
            'procedure_run_id' => $run->id,
            'procedure_step_run_id' => $stepRun?->id,
            'event_type' => $type->value,
            'payload' => $payload,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Still-ignorierende Variante: ohne verknüpften Run wird KEIN Event
     * geschrieben (bestehende Semantik, nicht stillschweigend verschärfen).
     *
     * @param array<string, mixed> $payload
     */
    protected function recordStepEvent(ProcedureStepRun $stepRun, ProcedureRunEventType $type, User $actor, array $payload): void {
        $run = $stepRun->run;
        if ($run === null) {
            return;
        }

        $this->recordRunEvent($run, $type, $actor, $stepRun, $payload);
    }

    /**
     * Konfigurations-Array der Step-Definition (leer bei fehlender Definition).
     *
     * @return array<string, mixed>
     */
    protected function stepConfig(ProcedureStepRun $stepRun): array {
        $config = $stepRun->stepDef?->config;

        return is_array($config) ? $config : [];
    }
}
