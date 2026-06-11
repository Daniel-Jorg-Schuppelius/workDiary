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

use App\Enums\Isms\{ControlImplementationStatus, ControlSource};
use App\Models\Isms\IsmsControl;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service ISMS-Maßnahmenkatalog & SoA (Feature 044, MVP 1).
 *
 * SoA-Regel (zentral hier durchgesetzt, zusätzlich zur Request-Validierung):
 * - applicable = false  ⇒ justification ist PFLICHT und
 *   implementation_status wird auf notApplicable gesetzt.
 * - applicable = true   ⇒ ein Status notApplicable wird auf open
 *   zurückgesetzt (sonst widerspräche die SoA-Aussage sich selbst).
 *
 * Annex-A-Import: idempotent per org+code-Upsert — bestehende Controls
 * (inkl. gepflegter SoA-Felder) werden NIE überschrieben.
 */
class ControlService {
    /**
     * Legt eine eigene Maßnahme an (source default custom).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsControl {
        $attributes = $this->applySoaRule($attributes, null);

        return DB::transaction(fn(): IsmsControl => IsmsControl::query()->create([
            'organization_id' => $creator->organization_id,
            'code' => trim((string) $attributes['code']),
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'source' => $attributes['source'] ?? ControlSource::Custom->value,
            'applicable' => (bool) ($attributes['applicable'] ?? true),
            'justification' => $attributes['justification'] ?? null,
            'implementation_status' => $attributes['implementation_status'] ?? ControlImplementationStatus::Open->value,
            'evidence_note' => $attributes['evidence_note'] ?? null,
            'owner_user_id' => $attributes['owner_user_id'] ?? null,
        ]));
    }

    /**
     * Aktualisiert eine Maßnahme (SoA-Felder, Status, Evidenz, Owner).
     * Code/Source von Annex-A-Controls bleiben unveränderlich (Referenz).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsControl $control, User $actor, array $attributes): IsmsControl {
        unset($actor);
        $attributes = $this->applySoaRule($attributes, $control);

        return DB::transaction(function () use ($control, $attributes): IsmsControl {
            $isCatalog = $control->source === ControlSource::Iso27001AnnexA;

            $control->update([
                'code' => $isCatalog ? $control->code : trim((string) ($attributes['code'] ?? $control->code)),
                'title' => $attributes['title'] ?? $control->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $control->description,
                'applicable' => array_key_exists('applicable', $attributes) ? (bool) $attributes['applicable'] : $control->applicable,
                'justification' => array_key_exists('justification', $attributes) ? $attributes['justification'] : $control->justification,
                'implementation_status' => $attributes['implementation_status'] ?? $control->implementation_status,
                'evidence_note' => array_key_exists('evidence_note', $attributes) ? $attributes['evidence_note'] : $control->evidence_note,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $control->owner_user_id,
            ]);

            return $control;
        });
    }

    /** Soft-Delete (nur eigene Maßnahmen sinnvoll, Policy: isms.manage). */
    public function delete(IsmsControl $control, User $actor): void {
        DB::transaction(function () use ($control, $actor): void {
            $control->audit('isms.control.deleted', ['actor_user_id' => $actor->id]);
            $control->risks()->detach();
            $control->delete();
        });
    }

    /**
     * Lädt den ISO/IEC 27001:2022-Annex-A-Referenzkatalog (93 Controls,
     * nur Code + Kurztitel — siehe config/isms-controls.php) in die
     * Organisation des Akteurs. Idempotent: vorhandene org+code-Einträge
     * bleiben unverändert (auch gelöschte werden NICHT neu angelegt —
     * der Unique-Index deckt soft-deleted Zeilen mit ab).
     *
     * @return int Anzahl neu angelegter Controls
     */
    public function importAnnexCatalog(User $actor): int {
        return DB::transaction(function () use ($actor): int {
            $existing = IsmsControl::query()
                ->withTrashed()
                ->pluck('code')
                ->flip();

            $created = 0;
            foreach ((array) config('isms-controls.controls', []) as $entry) {
                $code = (string) $entry['code'];
                if (isset($existing[$code])) {
                    continue;
                }

                IsmsControl::query()->create([
                    'organization_id' => $actor->organization_id,
                    'code' => $code,
                    'title' => (string) $entry['title'],
                    'description' => null,
                    'source' => ControlSource::Iso27001AnnexA->value,
                    'applicable' => true,
                    'implementation_status' => ControlImplementationStatus::Open->value,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Zentrale SoA-Regel (siehe Klassen-Doc).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException wenn applicable=false ohne Begründung
     */
    private function applySoaRule(array $attributes, ?IsmsControl $control): array {
        $applicable = array_key_exists('applicable', $attributes)
            ? (bool) $attributes['applicable']
            : ($control->applicable ?? true);

        if (! $applicable) {
            $justification = trim((string) ($attributes['justification']
                ?? $control->justification
                ?? ''));

            if ($justification === '') {
                throw ValidationException::withMessages([
                    'justification' => __('isms.error.justification_required'),
                ]);
            }

            $attributes['justification'] = $justification;
            $attributes['implementation_status'] = ControlImplementationStatus::NotApplicable->value;
        } elseif (($attributes['implementation_status'] ?? $control?->implementation_status?->value)
            === ControlImplementationStatus::NotApplicable->value
        ) {
            $attributes['implementation_status'] = ControlImplementationStatus::Open->value;
        }

        return $attributes;
    }
}
