<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Permit\PermitStatus;
use App\Models\{Organization, Permit};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permit>
 */
class PermitFactory extends Factory {
    protected $model = Permit::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => null,
            'title' => $this->faker->randomElement([
                'Sondernutzung Marktplatz',
                'Sperrzeitverkürzung',
                'GEMA-Anmeldung Sommerfest',
                'Schankerlaubnis',
            ]),
            'permit_type' => $this->faker->randomElement(['sondernutzung', 'sperrzeit', 'gema', 'schankerlaubnis']),
            'authority' => $this->faker->randomElement(['Ordnungsamt', 'Stadtverwaltung', 'GEMA', 'Gewerbeamt']),
            'reference_no' => $this->faker->optional()->bothify('AZ-####/##'),
            'status' => PermitStatus::Required->value,
            'applied_at' => null,
            'valid_from' => null,
            'valid_until' => null,
            'notes' => null,
        ];
    }

    public function granted(): self {
        return $this->state(fn(): array => [
            'status' => PermitStatus::Granted->value,
            'applied_at' => now()->subWeeks(3),
            'valid_from' => now()->subWeek(),
            'valid_until' => now()->addMonths(6),
        ]);
    }
}
