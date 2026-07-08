<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeExport;

use App\Models\CostCenterRule;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Löst die Kostenstelle eines Users für den Zeitexport auf (Rang 35 —
 * rescoped): Benutzer-Regel > Team-Regel (höchste Priorität, dann kleinste
 * id) > Org-Default-Regel (User und Team leer) > null. Zustandsbehaftet je
 * Export-Lauf (cached Regeln + Team-Zugehörigkeiten) — pro Aggregation neu
 * instanziieren.
 */
class CostCenterResolver {
    /** @var Collection<int, CostCenterRule>|null */
    private ?Collection $rules = null;

    /** @var array<int, array<int, int>> */
    private array $teamIdsByUser = [];

    public function __construct(private readonly int $organizationId) {}

    public function forUser(int $userId): ?string {
        $rules = $this->rules();
        if ($rules->isEmpty()) {
            return null;
        }

        $userRule = $rules->first(fn (CostCenterRule $r): bool => (int) $r->user_id === $userId);
        if ($userRule !== null) {
            return $userRule->cost_center;
        }

        $teamIds = $this->teamIdsFor($userId);
        if ($teamIds !== []) {
            $teamRule = $rules->first(fn (CostCenterRule $r): bool => $r->team_id !== null && in_array((int) $r->team_id, $teamIds, true));
            if ($teamRule !== null) {
                return $teamRule->cost_center;
            }
        }

        $default = $rules->first(fn (CostCenterRule $r): bool => $r->user_id === null && $r->team_id === null);

        return $default?->cost_center;
    }

    /**
     * Alle Regeln der Organisation, sortiert nach Priorität (absteigend) und
     * id (aufsteigend) — first() liefert damit den Gewinner.
     *
     * @return Collection<int, CostCenterRule>
     */
    private function rules(): Collection {
        return $this->rules ??= CostCenterRule::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $this->organizationId)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, int> */
    private function teamIdsFor(int $userId): array {
        return $this->teamIdsByUser[$userId] ??= DB::table('team_user')
            ->where('user_id', $userId)
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
