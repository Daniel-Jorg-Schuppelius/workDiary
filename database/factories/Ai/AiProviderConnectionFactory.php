<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderConnectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Ai;

use App\Enums\Ai\{AiConnectionStatus, AiFamily, AiProviderType};
use App\Models\Ai\AiProviderConnection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderConnection>
 */
class AiProviderConnectionFactory extends Factory {
    protected $model = AiProviderConnection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'family' => AiFamily::Llm,
            'provider' => AiProviderType::Fake,
            'name' => 'KI-Verbindung ' . fake()->unique()->numberBetween(1, 100000),
            'api_key' => 'test-key',
            'is_local' => false,
            'status' => AiConnectionStatus::Active,
        ];
    }

    public function local(): static {
        return $this->state(fn (): array => ['is_local' => true]);
    }

    public function translation(): static {
        return $this->state(fn (): array => ['family' => AiFamily::Translation]);
    }

    public function draft(): static {
        return $this->state(fn (): array => ['status' => AiConnectionStatus::Draft]);
    }

    public function blocked(): static {
        return $this->state(fn (): array => ['status' => AiConnectionStatus::Blocked]);
    }
}
