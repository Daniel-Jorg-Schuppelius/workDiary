<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\RequirementSource;
use App\Models\Isms\IsmsRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsRequirement>
 */
class IsmsRequirementFactory extends Factory {
    protected $model = IsmsRequirement::class;

    public function definition(): array {
        return [
            'norm' => 'Eigene',
            'edition' => '-',
            'ref_no' => 'M-' . fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(4),
            'source' => RequirementSource::Custom->value,
        ];
    }

    public function catalog(string $refNo = 'A.5.1', string $title = 'Informationssicherheitsrichtlinien'): self {
        return $this->state(fn() => [
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
            'ref_no' => $refNo,
            'title' => $title,
            'source' => RequirementSource::Catalog->value,
        ]);
    }
}
