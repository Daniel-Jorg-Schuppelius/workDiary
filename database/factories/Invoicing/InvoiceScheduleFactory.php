<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceScheduleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Invoicing;

use App\Models\Customer\Customer;
use App\Models\Invoicing\InvoiceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSchedule>
 */
class InvoiceScheduleFactory extends Factory {
    protected $model = InvoiceSchedule::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'customer_id' => Customer::factory(),
            'title' => 'Wartungsvertrag ' . fake()->sentence(2),
            'interval_unit' => InvoiceSchedule::UNIT_MONTH,
            'interval_count' => 1,
            'billing_period_mode' => InvoiceSchedule::MODE_PREVIOUS,
            'next_run_on' => now()->addMonth()->startOfMonth()->toDateString(),
            'status' => InvoiceSchedule::STATUS_ACTIVE,
        ];
    }
}
