<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Asset\MaintenanceIntervalKind;
use App\Models\{Asset, MaintenancePlan, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenancePlan> */
class MaintenancePlanFactory extends Factory {
    protected $model = MaintenancePlan::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'code' => strtoupper(fake()->bothify('MP-####')),
            'label' => fake()->words(3, true),
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 6,
            'tolerance_days' => 7,
            'procedure_template_code' => null,
            'last_run_at' => null,
            'next_due_on' => now()->addMonths(6)->toDateString(),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
