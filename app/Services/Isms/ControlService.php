<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControlService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Isms\{IsmsControl, IsmsRequirement};
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Domain-Service NORMNEUTRALE Maßnahmen (Feature 046, gemeinsamer
 * Managementsystem-Kern; vormals Maßnahmenkatalog + SoA aus Feature 044).
 *
 * Eine Maßnahme trägt Titel, Umsetzungsstatus, Nachweis-Notiz und Owner —
 * KEINE Normreferenz und KEINE SoA-Aussage mehr (beides liegt im
 * RequirementService: isms_requirements / isms_applicability_statements).
 * Anforderungs-Mappings (n:m, auch normübergreifend) laufen über
 * syncRequirements() — org-sicher analog RiskService::syncControls().
 */
class ControlService {
    use \App\Services\Isms\Concerns\SyncsScopedRelations;

    /**
     * Legt eine Maßnahme an.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsControl {
        return DB::transaction(function () use ($creator, $attributes): IsmsControl {
            $control = IsmsControl::query()->create([
                'organization_id' => $creator->organization_id,
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'implementation_status' => $attributes['implementation_status'] ?? ControlImplementationStatus::Open->value,
                'evidence_note' => $attributes['evidence_note'] ?? null,
                'owner_user_id' => $attributes['owner_user_id'] ?? null,
            ]);

            if (array_key_exists('requirement_ids', $attributes)) {
                $this->syncRequirements($control, $attributes['requirement_ids']);
            }

            return $control;
        });
    }

    /**
     * Aktualisiert eine Maßnahme (Stammdaten, Status, Nachweis, Owner,
     * Anforderungs-Mapping).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsControl $control, User $actor, array $attributes): IsmsControl {
        unset($actor);

        return DB::transaction(function () use ($control, $attributes): IsmsControl {
            $control->update([
                'title' => $attributes['title'] ?? $control->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $control->description,
                'implementation_status' => $attributes['implementation_status'] ?? $control->implementation_status,
                'evidence_note' => array_key_exists('evidence_note', $attributes) ? $attributes['evidence_note'] : $control->evidence_note,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $control->owner_user_id,
            ]);

            if (array_key_exists('requirement_ids', $attributes)) {
                $this->syncRequirements($control, $attributes['requirement_ids']);
            }

            return $control;
        });
    }

    /** Soft-Delete (Policy: isms.manage); Risiko-/Anforderungs-Mappings werden gelöst. */
    public function delete(IsmsControl $control, User $actor): void {
        DB::transaction(function () use ($control, $actor): void {
            $control->audit('isms.control.deleted', ['actor_user_id' => $actor->id]);
            $control->risks()->detach();
            $control->requirements()->detach();
            $control->delete();
        });
    }

    /**
     * Synchronisiert das Anforderungs-Mapping. Die IDs werden über die
     * org-gescopte Requirement-Query aufgelöst — fremde Organisationen
     * können dadurch nicht verknüpft werden (Pivot trägt bewusst keine
     * eigene organization_id, siehe Migration).
     *
     * Gemeinsamer org-gescopter Sync (Vollaudit 2026-07, N36).
     */
    public function syncRequirements(IsmsControl $control, mixed $requirementIds): void {
        $this->syncScopedIds($control->requirements(), IsmsRequirement::class, $requirementIds);
    }
}
