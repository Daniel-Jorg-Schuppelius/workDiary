<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestoreTestFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Backup\RestoreTestResult;
use App\Models\RestoreTest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestoreTest>
 */
class RestoreTestFactory extends Factory {
    protected $model = RestoreTest::class;

    public function definition(): array {
        $testedOn = CarbonImmutable::now()->subDays((int) $this->faker->numberBetween(0, 30));

        return [
            'source' => 'nightly',
            'tested_on' => $testedOn,
            'result' => RestoreTestResult::Passed,
            'scope' => 'db+storage',
            'restored_size_bytes' => $this->faker->numberBetween(1_000_000, 5_000_000_000),
            'duration_minutes' => $this->faker->numberBetween(5, 120),
            'notes' => null,
            'next_due_on' => $testedOn->addDays(180),
            'performed_by_user_id' => null,
        ];
    }

    public function failed(): static {
        return $this->state(fn(): array => ['result' => RestoreTestResult::Failed]);
    }

    public function partial(): static {
        return $this->state(fn(): array => ['result' => RestoreTestResult::Partial]);
    }

    public function testedOn(CarbonImmutable $date): static {
        return $this->state(fn(): array => ['tested_on' => $date]);
    }
}
