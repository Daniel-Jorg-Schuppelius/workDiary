<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CorrectiveActionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{CorrectiveActionStatus, FindingStatus};
use App\Models\Isms\{IsmsAuditFinding, IsmsCorrectiveAction};
use App\Models\User;
use App\Services\Isms\Concerns\ResolvesAuditReferences;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aggregat-Service Korrekturmaßnahmen (Feature 046, Inkrement C) — aus dem
 * AuditService herausgelöst (Refactoring Welle 2, B6b). Geschäftsregeln:
 * Anlage nur an offenen Feststellungen; Wirksamkeitsprüfung
 * (effective/ineffective) NUR mit Pflicht-Notiz; ineffective setzt die
 * Feststellung zurück auf inCorrection.
 */
class CorrectiveActionService {
    use ResolvesAuditReferences;

    /**
     * Legt eine Korrekturmaßnahme an (Status immer open) — nur solange die
     * Feststellung nicht geschlossen ist.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function createAction(IsmsAuditFinding $finding, User $actor, array $attributes): IsmsCorrectiveAction {
        if ($finding->status === FindingStatus::Closed) {
            throw ValidationException::withMessages([
                'finding' => __('isms.error.action_on_closed_finding'),
            ]);
        }

        return DB::transaction(function () use ($finding, $actor, $attributes): IsmsCorrectiveAction {
            return IsmsCorrectiveAction::query()->create([
                'organization_id' => $finding->organization_id,
                'isms_audit_finding_id' => $finding->id,
                'title' => $attributes['title'],
                'root_cause' => $attributes['root_cause'] ?? null,
                'action_plan' => $attributes['action_plan'] ?? null,
                'owner_user_id' => $this->resolveUserId($attributes['owner_user_id'] ?? null, (int) $actor->organization_id, 'owner_user_id'),
                'due_on' => $attributes['due_on'] ?? null,
                'status' => CorrectiveActionStatus::Open->value,
            ]);
        });
    }

    /**
     * Aktualisiert Stammdaten einer Korrekturmaßnahme — Status bleibt
     * unangetastet (Übergänge über transitionAction()).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateAction(IsmsCorrectiveAction $action, User $actor, array $attributes): IsmsCorrectiveAction {
        return DB::transaction(function () use ($action, $actor, $attributes): IsmsCorrectiveAction {
            $action->update([
                'title' => $attributes['title'] ?? $action->title,
                'root_cause' => array_key_exists('root_cause', $attributes) ? $attributes['root_cause'] : $action->root_cause,
                'action_plan' => array_key_exists('action_plan', $attributes) ? $attributes['action_plan'] : $action->action_plan,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes)
                    ? $this->resolveUserId($attributes['owner_user_id'], (int) $actor->organization_id, 'owner_user_id')
                    : $action->owner_user_id,
                'due_on' => array_key_exists('due_on', $attributes) ? $attributes['due_on'] : $action->due_on,
            ]);

            return $action;
        });
    }

    /**
     * Statusübergang entlang {@see CorrectiveActionStatus::allowedTransitions()}:
     * - done setzt completed_on (heute, falls nicht mitgegeben);
     * - effective/ineffective (Wirksamkeitsprüfung) erfordern die
     *   Pflicht-Notiz effectiveness_note;
     * - ineffective setzt die zugehörige Feststellung zurück auf
     *   inCorrection (auditiert, sofern nicht bereits inCorrection).
     *
     * @throws ValidationException
     */
    public function transitionAction(
        IsmsCorrectiveAction $action,
        CorrectiveActionStatus $target,
        User $actor,
        ?string $effectivenessNote = null,
    ): IsmsCorrectiveAction {
        if ($action->status === $target) {
            return $action;
        }

        if (! in_array($target, $action->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $action->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        $isEffectivenessCheck = in_array($target, [CorrectiveActionStatus::Effective, CorrectiveActionStatus::Ineffective], true);
        if ($isEffectivenessCheck && trim((string) $effectivenessNote) === '') {
            throw ValidationException::withMessages([
                'effectiveness_note' => __('isms.error.effectiveness_note_required'),
            ]);
        }

        return DB::transaction(function () use ($action, $target, $actor, $effectivenessNote, $isEffectivenessCheck): IsmsCorrectiveAction {
            $from = $action->status;

            $action->update([
                'status' => $target->value,
                'completed_on' => $target === CorrectiveActionStatus::Done
                    ? ($action->completed_on ?? Carbon::today())
                    : $action->completed_on,
                'effectiveness_note' => $isEffectivenessCheck
                    ? trim((string) $effectivenessNote)
                    : $action->effectiveness_note,
            ]);

            $action->audit('isms.corrective_action.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            // Unwirksame Maßnahme: Feststellung zurück in die Korrektur
            // (bewusste Service-Rücksetzung außerhalb der Nutzer-Kette).
            if ($target === CorrectiveActionStatus::Ineffective) {
                $finding = $action->finding()->first();
                if ($finding !== null && $finding->status !== FindingStatus::InCorrection) {
                    $findingFrom = $finding->status;
                    $finding->update(['status' => FindingStatus::InCorrection->value]);
                    $finding->audit('isms.finding.reverted_to_correction', [
                        'actor_user_id' => $actor->id,
                        'from' => $findingFrom->value,
                        'to' => FindingStatus::InCorrection->value,
                        'corrective_action_id' => $action->id,
                    ]);
                }
            }

            return $action;
        });
    }

    /** Soft-Delete einer Korrekturmaßnahme. */
    public function deleteAction(IsmsCorrectiveAction $action, User $actor): void {
        DB::transaction(function () use ($action, $actor): void {
            $action->audit('isms.corrective_action.deleted', ['actor_user_id' => $actor->id]);
            $action->delete();
        });
    }
}
