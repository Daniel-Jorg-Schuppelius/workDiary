<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareProductFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{SoftwareCategory, SupportStatus};
use App\Models\Isms\IsmsSoftwareProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsSoftwareProduct>
 */
class IsmsSoftwareProductFactory extends Factory {
    protected $model = IsmsSoftwareProduct::class;

    public function definition(): array {
        return [
            'name' => fake()->unique()->words(2, true),
            'vendor' => fake()->company(),
            'product_version' => fake()->semver(),
            'category' => fake()->randomElement(SoftwareCategory::cases())->value,
            'owner_user_id' => null,
            'support_status' => SupportStatus::Supported->value,
            'eol_on' => null,
            'notes' => null,
        ];
    }

    public function endOfLife(): self {
        return $this->state(fn() => [
            'support_status' => SupportStatus::EndOfLife->value,
            'eol_on' => now()->subMonth()->toDateString(),
        ]);
    }
}
