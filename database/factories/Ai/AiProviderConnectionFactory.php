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
use App\Models\Platform\Organization;
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
            // Datenschutzfreigabe (MVP-1672): neue Organisationen verlangen sie —
            // die Vorgabe ist deshalb eine freigegebene Verbindung, {@see unapproved()} das Gegenteil.
            'has_dpa' => true,
            'is_eu_processing' => true,
            'is_training_excluded' => true,
            'privacy_reviewed_at' => now(),
        ];
    }

    /** Ohne Datenschutzfreigabe (nie geprüft). */
    public function unapproved(): static {
        return $this->state(fn (): array => [
            'has_dpa' => false,
            'is_eu_processing' => false,
            'is_training_excluded' => false,
            'privacy_reviewed_at' => null,
        ]);
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
