<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalFlowService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ApprovalDecision;
use App\Models\{ApprovalStep, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * MVP-531: generisches Antragsverfahren — konfigurierbare Genehmigungsstufen
 * je Antragstyp (Org-Setting), Vier-Augen über das Stufen-Journal.
 *
 * Zuständigkeit bewusst schmal: der Service zählt/verbucht Stufen und
 * erzwingt Vier-Augen; Statuswechsel, Audit-Events und Benachrichtigungen
 * bleiben in den Fach-Flows (VacationController, OvertimeRequestService,
 * TimeCorrectionService). Ablehnung ist auf jeder Stufe final.
 */
class ApprovalFlowService {
    public const TYPE_VACATION = 'vacation';

    public const TYPE_OVERTIME = 'overtime';

    public const TYPE_TIME_CORRECTION = 'time_correction';

    /**
     * Konfigurierte Stufenzahl (1–2) für den Antragstyp. Urlaub liest den
     * bestehenden MVP-523-Pfad `vacation.approval_stages`, alle weiteren
     * Typen `approvals.<typ>_stages`.
     */
    public function stagesFor(string $type): int {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $settings = $org?->settings;

        $raw = $type === self::TYPE_VACATION
            ? data_get($settings, 'vacation.approval_stages', 1)
            : data_get($settings, 'approvals.' . $type . '_stages', 1);

        return max(1, min(2, (int) $raw));
    }

    /**
     * Genehmigungsstufe verbuchen. Wirft bei Vier-Augen-Verstoß (dieselbe
     * Person hat bereits eine Stufe dieses Antrags entschieden).
     *
     * @throws ValidationException
     */
    public function approveStage(Model $approvable, string $type, User $decider, ?string $comment = null): ApprovalProgress {
        $required = $this->stagesFor($type);
        $done = $this->approvedSteps($approvable);

        if ($this->hasDecided($approvable, $decider)) {
            throw ValidationException::withMessages([
                'decided_by' => __('Die zweite Freigabe muss durch eine andere Person erfolgen (Vier-Augen-Prinzip).'),
            ]);
        }

        ApprovalStep::query()->create([
            'organization_id' => $approvable->getAttribute('organization_id'),
            'approvable_type' => $approvable::class,
            'approvable_id' => (int) $approvable->getKey(),
            'stage' => $done + 1,
            'decision' => ApprovalDecision::Approved->value,
            'decided_by' => (int) $decider->getKey(),
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        ]);

        return new ApprovalProgress($done + 1, $required);
    }

    /**
     * Ablehnungsstufe verbuchen — Ablehnung ist immer final; der Fach-Flow
     * setzt den Antragsstatus selbst.
     */
    public function rejectStage(Model $approvable, User $decider, ?string $comment = null): void {
        ApprovalStep::query()->create([
            'organization_id' => $approvable->getAttribute('organization_id'),
            'approvable_type' => $approvable::class,
            'approvable_id' => (int) $approvable->getKey(),
            'stage' => $this->approvedSteps($approvable) + 1,
            'decision' => ApprovalDecision::Rejected->value,
            'decided_by' => (int) $decider->getKey(),
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        ]);
    }

    /**
     * Übergangs-Backfill: Alt-Anträge, deren erste Freigabe noch vor dem
     * Framework erfasst wurde (z. B. Vacation.first_approved_by ohne Step),
     * bekommen den fehlenden Schritt nachgetragen, damit Zählung und
     * Vier-Augen greifen.
     */
    public function backfillStage(Model $approvable, int $deciderId, ?\DateTimeInterface $decidedAt = null): void {
        if ($this->approvedSteps($approvable) > 0) {
            return;
        }
        $step = ApprovalStep::query()->create([
            'organization_id' => $approvable->getAttribute('organization_id'),
            'approvable_type' => $approvable::class,
            'approvable_id' => (int) $approvable->getKey(),
            'stage' => 1,
            'decision' => ApprovalDecision::Approved->value,
            'decided_by' => $deciderId,
        ]);
        if ($decidedAt !== null) {
            $step->forceFill(['created_at' => $decidedAt])->save();
        }
    }

    public function progressFor(Model $approvable, string $type): ApprovalProgress {
        return new ApprovalProgress($this->approvedSteps($approvable), $this->stagesFor($type));
    }

    private function approvedSteps(Model $approvable): int {
        return ApprovalStep::query()
            ->where('approvable_type', $approvable::class)
            ->where('approvable_id', (int) $approvable->getKey())
            ->where('decision', ApprovalDecision::Approved->value)
            ->count();
    }

    private function hasDecided(Model $approvable, User $decider): bool {
        return ApprovalStep::query()
            ->where('approvable_type', $approvable::class)
            ->where('approvable_id', (int) $approvable->getKey())
            ->where('decided_by', (int) $decider->getKey())
            ->exists();
    }
}
