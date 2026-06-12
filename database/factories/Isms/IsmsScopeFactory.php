<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsScopeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Models\Isms\IsmsScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsScope>
 */
class IsmsScopeFactory extends Factory {
    protected $model = IsmsScope::class;

    public function definition(): array {
        return [
            'name' => fake()->words(3, true),
            'description' => null,
            'is_default' => false,
        ];
    }

    public function default(): self {
        return $this->state(fn() => [
            'name' => 'Gesamtorganisation',
            'is_default' => true,
        ]);
    }
}
