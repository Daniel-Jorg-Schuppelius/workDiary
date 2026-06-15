<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDefectFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Asset\{DefectSeverity, DefectStatus};
use App\Models\{Asset, AssetDefect, Organization, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetDefect> */
class AssetDefectFactory extends Factory {
    protected $model = AssetDefect::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'reported_by_user_id' => User::factory(),
            'reported_at' => now(),
            'severity' => DefectSeverity::Medium->value,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'status' => DefectStatus::Open->value,
            'blocks_usage' => false,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            'resolution_note' => null,
        ];
    }

    public function blocking(): static {
        return $this->state(fn(): array => [
            'blocks_usage' => true,
            'severity' => DefectSeverity::High->value,
            'status' => DefectStatus::Open->value,
        ]);
    }

    public function resolved(): static {
        return $this->state(fn(): array => [
            'status' => DefectStatus::Resolved->value,
            'resolved_at' => now(),
            'resolution_note' => fake()->sentence(),
        ]);
    }
}
