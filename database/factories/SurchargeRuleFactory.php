<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeRuleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Surcharge\SurchargeKind;
use App\Models\Organization;
use App\Models\Surcharge\SurchargeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Beispielregeln (Nacht 25 %, So 50 %, Feiertag 125 %, Sa 20 %) leben
 * bewusst NUR hier (Tests/Demo) — keine Produktions-Seeds.
 *
 * @extends Factory<SurchargeRule>
 */
class SurchargeRuleFactory extends Factory {
    protected $model = SurchargeRule::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'code' => 'night-' . fake()->unique()->numberBetween(1, 9999),
            'label' => 'Nachtzuschlag',
            'kind' => SurchargeKind::Night->value,
            'window_start' => '23:00:00',
            'window_end' => '06:00:00',
            'percentage' => '25.00',
            'wage_type_code' => '2010',
            'priority' => 0,
            'active' => true,
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function night(string $from = '23:00:00', string $to = '06:00:00', string $percentage = '25.00'): self {
        return $this->state(fn(): array => [
            'kind' => SurchargeKind::Night->value,
            'label' => 'Nachtzuschlag',
            'window_start' => $from,
            'window_end' => $to,
            'percentage' => $percentage,
            'wage_type_code' => '2010',
        ]);
    }

    public function saturday(string $percentage = '20.00'): self {
        return $this->state(fn(): array => [
            'code' => 'saturday-' . fake()->unique()->numberBetween(1, 9999),
            'kind' => SurchargeKind::Saturday->value,
            'label' => 'Samstagszuschlag',
            'window_start' => null,
            'window_end' => null,
            'percentage' => $percentage,
            'wage_type_code' => '2020',
        ]);
    }

    public function sunday(string $percentage = '50.00'): self {
        return $this->state(fn(): array => [
            'code' => 'sunday-' . fake()->unique()->numberBetween(1, 9999),
            'kind' => SurchargeKind::Sunday->value,
            'label' => 'Sonntagszuschlag',
            'window_start' => null,
            'window_end' => null,
            'percentage' => $percentage,
            'wage_type_code' => '2030',
        ]);
    }

    public function holiday(string $percentage = '125.00'): self {
        return $this->state(fn(): array => [
            'code' => 'holiday-' . fake()->unique()->numberBetween(1, 9999),
            'kind' => SurchargeKind::Holiday->value,
            'label' => 'Feiertagszuschlag',
            'window_start' => null,
            'window_end' => null,
            'percentage' => $percentage,
            'wage_type_code' => '2040',
        ]);
    }

    public function custom(string $from, string $to, string $percentage = '10.00'): self {
        return $this->state(fn(): array => [
            'code' => 'custom-' . fake()->unique()->numberBetween(1, 9999),
            'kind' => SurchargeKind::Custom->value,
            'label' => 'Sonderzuschlag',
            'window_start' => $from,
            'window_end' => $to,
            'percentage' => $percentage,
            'wage_type_code' => '2090',
        ]);
    }

    public function inactive(): self {
        return $this->state(fn(): array => ['active' => false]);
    }
}
