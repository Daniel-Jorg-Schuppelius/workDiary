<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNoteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\CarryForward;

use App\Models\CarryForward\CarryForwardNote;
use App\Models\Platform\Organization;
use App\Support\Period\PeriodKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Offener Merker einer erfundenen Reihe, gültig ab 2027.
 *
 * @extends Factory<CarryForwardNote>
 */
class CarryForwardNoteFactory extends Factory {
    protected $model = CarryForwardNote::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $target = PeriodKey::parse('2027');

        return [
            'organization_id' => Organization::factory(),
            'series_key' => 'test:' . $this->faker->unique()->numberBetween(1, 999999),
            'period_key' => $target,
            'valid_from' => $target->start(),
            'note' => $this->faker->sentence(),
            'origin_period_key' => $target->previous(),
        ];
    }

    /** Zielperiode setzen (Schlüssel und Beginn). */
    public function from(string $periodKey): static {
        $target = PeriodKey::parse($periodKey);

        return $this->state(fn (): array => ['period_key' => $target, 'valid_from' => $target->start()]);
    }
}
