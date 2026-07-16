<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProjectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Domain;

use App\Enums\Domain\{DomainRenewalMode, DomainSyncStatus};
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainProjection>
 */
class DomainProjectionFactory extends Factory {
    protected $model = DomainProjection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $domain = fake()->unique()->domainName();

        return [
            'connection_id' => DomainProviderConnection::factory(),
            'external_domain' => $domain,
            'domain_hash' => DomainProjection::hashFor($domain),
            'external_user' => 'reseller1',
            'registrar' => 'DomainReselling',
            'status' => 'ACTIVE',
            'sync_status' => DomainSyncStatus::Current,
            'renewal_mode' => DomainRenewalMode::Autorenew->value,
            'expiration_at' => now()->addMonths(6),
            'renewal_price' => 12.50,
            'renewal_currency' => 'EUR',
            'synced_at' => now(),
        ];
    }

    public function expiringIn(int $days): static {
        return $this->state(fn (): array => ['expiration_at' => now()->addDays($days)]);
    }

    public function risky(): static {
        return $this->state(fn (): array => ['renewal_mode' => DomainRenewalMode::Autodelete->value]);
    }
}
