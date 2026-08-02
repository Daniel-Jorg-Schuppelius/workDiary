<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Disposal;

use App\Models\Disposal\{DisposalItem, DisposalJob};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisposalItem> */
class DisposalItemFactory extends Factory {
    protected $model = DisposalItem::class;

    public function definition(): array {
        return [
            'disposal_job_id' => DisposalJob::factory(),
            'sort_order' => 1,
            'category' => fake()->randomElement(['PC', 'Server', 'Monitor', 'Drucker', 'Router']),
            'manufacturer' => fake()->company(),
            'serial_number' => strtoupper(fake()->bothify('SN-########')),
            'quantity' => 1,
            'avv_code' => '20 01 36',
            'is_hazardous' => false,
            'has_data_storage' => false,
        ];
    }

    /** Gefährlicher Abfall (`*`-AVV-Schlüssel). */
    public function hazardous(): static {
        return $this->state(fn(): array => [
            'avv_code' => '20 01 35*',
            'is_hazardous' => true,
        ]);
    }

    /** Datentragendes Gerät (Behandlungs-Pflicht vor Abschluss). */
    public function withDataStorage(): static {
        return $this->state(fn(): array => [
            'has_data_storage' => true,
        ]);
    }
}
