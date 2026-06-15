<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAssignmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Asset, AssetAssignment, Organization, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetAssignment> */
class AssetAssignmentFactory extends Factory {
    protected $model = AssetAssignment::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'assigned_to_user_id' => User::factory(),
            'assigned_to_team_id' => null,
            'diary_entry_id' => null,
            'checked_out_at' => now(),
            'checked_out_by_user_id' => null,
            'expected_return_at' => now()->addDays(7),
            'returned_at' => null,
            'returned_by_user_id' => null,
            'condition_out' => null,
            'condition_in' => null,
            'note' => null,
        ];
    }

    public function returned(): static {
        return $this->state(fn(): array => [
            'returned_at' => now(),
        ]);
    }

    public function overdue(): static {
        return $this->state(fn(): array => [
            'expected_return_at' => now()->subDays(2),
            'returned_at' => null,
        ]);
    }
}
