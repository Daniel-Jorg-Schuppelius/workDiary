<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderConnectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Domain;

use App\Enums\Domain\{DomainConnectionStatus, DomainProviderEnvironment};
use App\Models\Domain\DomainProviderConnection;
use App\Models\Organization;
use App\Plugins\Support\Domain\DomainCapabilityMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainProviderConnection>
 */
class DomainProviderConnectionFactory extends Factory {
    protected $model = DomainProviderConnection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'environment' => DomainProviderEnvironment::Production,
            'name' => 'DomainReselling ' . fake()->company(),
            'endpoint' => 'domainreselling',
            'login' => 'reseller' . fake()->numberBetween(1, 999),
            'password' => 'secret-pw',
            'status' => DomainConnectionStatus::Active,
            'capabilities' => DomainCapabilityMatrix::default()->toArray(),
        ];
    }

    public function draft(): static {
        return $this->state(fn (): array => ['status' => DomainConnectionStatus::Draft]);
    }

    public function pilotConfirmed(): static {
        return $this->state(fn (): array => ['pilot_confirmed_at' => now()]);
    }
}
