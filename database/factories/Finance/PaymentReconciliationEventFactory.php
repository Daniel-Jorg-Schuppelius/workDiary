<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReconciliationEventFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{BankTransaction, PaymentReconciliationEvent};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentReconciliationEvent>
 *
 * prev_hash/hash setzt der HashChained-Trait im creating-Event selbst —
 * die Factory liefert nur die fachlichen Felder (Rohwerte im Payload!).
 */
class PaymentReconciliationEventFactory extends Factory {
    protected $model = PaymentReconciliationEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'bank_transaction_id' => BankTransaction::factory(),
            // Kette ist scope-frei — die Org kommt aus dem Umsatz, nicht aus dem Binding.
            'organization_id' => fn (array $attributes): ?int => BankTransaction::withoutGlobalScopes()
                ->find($attributes['bank_transaction_id'])?->organization_id,
            'event' => 'confirmed',
            'payload' => ['amount' => '100.00'],
        ];
    }
}
