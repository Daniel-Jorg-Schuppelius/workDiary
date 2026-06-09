<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanModuleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Models\{Organization, PlanModuleGrace};
use Illuminate\Support\Carbon;

/**
 * Verwaltet den Downgrade-/Karenz-Lebenszyklus der Modul-Freischaltung.
 * Bei einem Plan-Downgrade verlorene Module landen mit Karenzfrist im
 * Ledger (plan_module_grace); ein Re-Upgrade hebt offene Karenz wieder auf.
 */
class PlanModuleService {
    /**
     * Modul-Codes (module.*) eines Plans laut Katalog.
     *
     * @return list<string>
     */
    public function modulesForPlan(?string $plan): array {
        /** @var list<string> $codes */
        $codes = (array) config('plans.tiers.' . ($plan !== null && $plan !== '' ? $plan : 'free'), []);

        return array_values(array_filter($codes, static fn (string $c): bool => str_starts_with($c, 'module.')));
    }

    /**
     * Reagiert auf einen Plan-Wechsel: verlorene Module erhalten eine Karenz,
     * neu gewonnene Module heben offene (noch nicht verarbeitete) Karenz auf.
     */
    public function handlePlanChange(Organization $organization, ?string $oldPlan, ?string $newPlan): void {
        $old = $this->modulesForPlan($oldPlan);
        $new = $this->modulesForPlan($newPlan);

        $lost = array_values(array_diff($old, $new));
        $gained = array_values(array_diff($new, $old));
        $graceDays = (int) config('plans.grace_days', 30);

        foreach ($lost as $module) {
            PlanModuleGrace::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'module' => $module],
                [
                    'lost_at' => Carbon::now(),
                    'grace_until' => Carbon::now()->addDays($graceDays),
                    'purged_at' => null,
                ],
            );
        }

        if ($gained !== []) {
            PlanModuleGrace::query()
                ->where('organization_id', $organization->id)
                ->whereIn('module', $gained)
                ->whereNull('purged_at')
                ->delete();
        }
    }

    /**
     * Module, die aktuell in laufender Karenz sind (Zugriff bleibt erhalten).
     *
     * @return list<string>
     */
    public function activeGraceModules(int $organizationId): array {
        /** @var list<string> $modules */
        $modules = PlanModuleGrace::query()
            ->where('organization_id', $organizationId)
            ->whereNull('purged_at')
            ->where('grace_until', '>', Carbon::now())
            ->pluck('module')
            ->all();

        return $modules;
    }
}
