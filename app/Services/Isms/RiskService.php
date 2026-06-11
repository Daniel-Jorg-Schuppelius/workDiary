<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\RiskStatus;
use App\Models\Isms\{IsmsControl, IsmsRisk};
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service ISMS-Risikoregister (Feature 044, MVP 1).
 *
 * Geschäftsregeln:
 * - risk_no: laufende Nummer je Organisation (Vergabe in der Transaktion,
 *   Unique-Index isms_risks_org_no_uq sichert Kollisionen ab).
 * - score = likelihood * impact, wird hier berechnet und persistiert
 *   (Sortierung/Matrix laufen über die Spalte).
 * - Statusübergänge ausschließlich über transition() entlang
 *   RiskStatus::allowedTransitions().
 *
 * Audit läuft über den Auditable-Trait (created/updated/deleted) plus
 * gezielte audit()-Events für Statusübergänge — eine eigene Event-Tabelle
 * gibt es bewusst nicht (Lebenszyklus trivial, analog Wissensbasis).
 */
class RiskService {
    /**
     * Legt ein Risiko an (Status default identified).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsRisk {
        return DB::transaction(function () use ($creator, $attributes): IsmsRisk {
            $likelihood = (int) $attributes['likelihood'];
            $impact = (int) $attributes['impact'];

            $risk = IsmsRisk::query()->create([
                'organization_id' => $creator->organization_id,
                'risk_no' => $this->nextRiskNo((int) $creator->organization_id),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'],
                'asset_ref' => $attributes['asset_ref'] ?? null,
                'threat' => $attributes['threat'] ?? null,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'score' => $likelihood * $impact,
                'treatment' => $attributes['treatment'],
                'status' => RiskStatus::Identified->value,
                'owner_user_id' => $attributes['owner_user_id'] ?? null,
                'review_due_on' => $attributes['review_due_on'] ?? null,
            ]);

            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($risk, $this->normalizeControlIds($attributes['control_ids']));
            }

            return $risk;
        });
    }

    /**
     * Aktualisiert Stammdaten/Bewertung (Score wird neu berechnet);
     * der Status bleibt unangetastet — Übergänge laufen über transition().
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsRisk $risk, User $actor, array $attributes): IsmsRisk {
        return DB::transaction(function () use ($risk, $actor, $attributes): IsmsRisk {
            unset($actor);

            $likelihood = (int) ($attributes['likelihood'] ?? $risk->likelihood);
            $impact = (int) ($attributes['impact'] ?? $risk->impact);

            $risk->update([
                'title' => $attributes['title'] ?? $risk->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $risk->description,
                'category' => $attributes['category'] ?? $risk->category,
                'asset_ref' => array_key_exists('asset_ref', $attributes) ? $attributes['asset_ref'] : $risk->asset_ref,
                'threat' => array_key_exists('threat', $attributes) ? $attributes['threat'] : $risk->threat,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'score' => $likelihood * $impact,
                'treatment' => $attributes['treatment'] ?? $risk->treatment,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $risk->owner_user_id,
                'review_due_on' => array_key_exists('review_due_on', $attributes) ? $attributes['review_due_on'] : $risk->review_due_on,
            ]);

            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($risk, $this->normalizeControlIds($attributes['control_ids']));
            }

            return $risk;
        });
    }

    /**
     * Statusübergang entlang der State-Machine ({@see RiskStatus::allowedTransitions()}).
     *
     * @throws ValidationException bei unzulässigem Übergang
     */
    public function transition(IsmsRisk $risk, RiskStatus $target, User $actor): IsmsRisk {
        if ($risk->status === $target) {
            return $risk;
        }

        if (! in_array($target, $risk->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $risk->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($risk, $target, $actor): IsmsRisk {
            $from = $risk->status;
            $risk->update(['status' => $target->value]);
            $risk->audit('isms.risk.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $risk;
        });
    }

    /** Soft-Delete (Policy: isms.manage bzw. Admin). */
    public function delete(IsmsRisk $risk, User $actor): void {
        DB::transaction(function () use ($risk, $actor): void {
            // Fachliches Event VOR dem Delete, damit es gemeinsam mit dem
            // Auditable-`deleted` in der Hash-Kette landet.
            $risk->audit('isms.risk.deleted', ['actor_user_id' => $actor->id]);
            $risk->controls()->detach();
            $risk->delete();
        });
    }

    /**
     * Synchronisiert die Maßnahmen-Zuordnung. Die IDs werden über die
     * org-gescopte Control-Query aufgelöst — fremde Organisationen können
     * dadurch nicht verknüpft werden (Pivot trägt bewusst keine eigene
     * organization_id, siehe Migration).
     *
     * @param  list<int|string>  $controlIds
     */
    public function syncControls(IsmsRisk $risk, array $controlIds): void {
        $ids = IsmsControl::query()
            ->whereIn('id', array_map(intval(...), $controlIds))
            ->pluck('id')
            ->all();

        $risk->controls()->sync($ids);
    }

    /**
     * Normalisiert rohe Request-Werte zu einer ID-Liste (nur int/string).
     *
     * @return list<int|string>
     */
    private function normalizeControlIds(mixed $value): array {
        return array_values(array_filter(
            (array) $value,
            static fn(mixed $id): bool => is_int($id) || is_string($id),
        ));
    }

    /**
     * Daten für das 5x5-Matrix-Widget: [likelihood][impact] => Anzahl
     * offener Risiken (fehlende Zellen = 0).
     *
     * @return array<int, array<int, int>>
     */
    public function matrix(): array {
        $cells = [];
        foreach (range(1, 5) as $likelihood) {
            foreach (range(1, 5) as $impact) {
                $cells[$likelihood][$impact] = 0;
            }
        }

        foreach (IsmsRisk::query()->matrixCells()->get() as $row) {
            $cells[(int) $row->likelihood][(int) $row->impact] = (int) $row->risk_count;
        }

        return $cells;
    }

    /** Nächste laufende Risiko-Nummer der Organisation (innerhalb der Transaktion). */
    private function nextRiskNo(int $organizationId): int {
        $max = IsmsRisk::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max('risk_no');

        return ((int) $max) + 1;
    }
}
