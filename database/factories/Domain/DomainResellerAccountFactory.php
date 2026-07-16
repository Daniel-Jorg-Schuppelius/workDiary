<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellerAccountFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Domain;

use App\Models\Domain\{DomainProviderConnection, DomainResellerAccount};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainResellerAccount>
 */
class DomainResellerAccountFactory extends Factory {
    protected $model = DomainResellerAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'connection_id' => DomainProviderConnection::factory(),
            'external_user' => 'sub' . fake()->numberBetween(1, 999),
            'parent_user' => 'reseller1',
            'depth' => 1,
            'user_class' => 'RESELLER',
            'active' => true,
            'currency' => 'EUR',
            'balance_snapshot' => 100.00,
            'synced_at' => now(),
        ];
    }
}
