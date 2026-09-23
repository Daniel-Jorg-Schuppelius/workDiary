<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\{Organization, TextCorrection};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TextCorrection>
 */
class TextCorrectionFactory extends Factory {
    protected $model = TextCorrection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'wrong' => 'serverwartunng',
            'correct' => 'Serverwartung',
            'origin' => TextCorrection::ORIGIN_MANUAL,
            'active' => true,
        ];
    }

    public function learned(): static {
        return $this->state(fn (): array => ['origin' => TextCorrection::ORIGIN_LEARNED]);
    }

    public function inactive(): static {
        return $this->state(fn (): array => ['active' => false]);
    }
}
