<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaWorkflowService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Privacy\{Dpia, DpiaStep};
use App\Models\User;
use RuntimeException;

/**
 * Geführter DSFA-Schritt-Workflow (Nachtrag 043a): erzwingt die Reihenfolge
 * Beschreibung → Notwendigkeit → Verhältnismäßigkeit/Risiken → Maßnahmen →
 * Freigabe. Die inhaltlichen Schritte schreiben ihr Ergebnis zusätzlich in
 * die bestehenden {@see Dpia}-Felder (necessity/risks/mitigations), sodass
 * Alt-Auswertungen und der Compliance-Check unverändert funktionieren; die
 * Freigabe setzt outcome/assessed_by/assessed_at.
 */
class DpiaWorkflowService {
    /** Legt fehlende Workflow-Schritte idempotent an. */
    public function ensureSteps(Dpia $dpia): void {
        foreach (DpiaStep::STEPS as $position => $step) {
            DpiaStep::query()->firstOrCreate(
                ['dpia_id' => $dpia->id, 'step' => $step],
                [
                    'organization_id' => $dpia->organization_id,
                    'position' => $position,
                    'status' => DpiaStep::STATUS_PENDING,
                ],
            );
        }
    }

    /**
     * Schließt einen Schritt ab. Wirft, wenn ein früherer Schritt offen ist
     * oder der Freigabe-Schritt ohne Ergebnis abgeschlossen werden soll.
     */
    public function complete(DpiaStep $step, User $actor, ?string $content, ?string $outcome = null, ?string $residualRisk = null): DpiaStep {
        if ($step->isDone()) {
            return $step;
        }

        $openBefore = DpiaStep::query()
            ->where('dpia_id', $step->dpia_id)
            ->where('position', '<', $step->position)
            ->where('status', '!=', DpiaStep::STATUS_DONE)
            ->exists();
        if ($openBefore) {
            throw new RuntimeException('Frühere DSFA-Schritte sind noch offen.');
        }

        /** @var Dpia $dpia */
        $dpia = $step->dpia()->firstOrFail();

        if ($step->step === 'approval') {
            if (! in_array($outcome, ['proceed', 'consult', 'abort'], true)) {
                throw new RuntimeException('Die Freigabe benötigt ein Ergebnis (proceed/consult/abort).');
            }
            $dpia->forceFill([
                'outcome' => $outcome,
                'residual_risk' => $residualRisk ?? $dpia->residual_risk,
                'assessed_by' => $actor->id,
                'assessed_at' => now(),
            ])->save();
        } elseif (in_array($step->step, ['necessity', 'risks', 'mitigations'], true)) {
            $dpia->forceFill([$step->step => $content])->save();
        }

        $step->forceFill([
            'content' => $content,
            'status' => DpiaStep::STATUS_DONE,
            'completed_by' => $actor->id,
            'completed_at' => now(),
        ])->save();

        return $step;
    }

    /** Nächster offener Schritt (null = Workflow abgeschlossen). */
    public function nextStep(Dpia $dpia): ?DpiaStep {
        return DpiaStep::query()
            ->where('dpia_id', $dpia->id)
            ->where('status', '!=', DpiaStep::STATUS_DONE)
            ->orderBy('position')
            ->first();
    }
}
