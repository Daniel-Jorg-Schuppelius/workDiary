<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferEventFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{BillingTransfer, BillingTransferEvent};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingTransferEvent>
 *
 * prev_hash/hash setzt der HashChained-Trait im creating-Event selbst —
 * die Factory liefert nur die fachlichen Felder (Rohwerte im Payload!).
 */
class BillingTransferEventFactory extends Factory {
    protected $model = BillingTransferEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'billing_transfer_id' => BillingTransfer::factory(),
            // Kette ist scope-frei — die Org kommt aus dem Transfer, nicht aus dem Binding.
            'organization_id' => fn (array $attributes): ?int => BillingTransfer::withoutGlobalScopes()
                ->find($attributes['billing_transfer_id'])?->organization_id,
            'event' => 'created',
            'payload' => ['status' => 'draft'],
        ];
    }
}
