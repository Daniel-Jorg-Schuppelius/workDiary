<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory {
    protected $model = Customer::class;

    public function definition(): array {
        $company = fake()->company();

        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'name' => $company,
            'number' => null, // Auto-Nummer via Customer::booted()
            'company' => $company,
            'vat_id' => null,
            'contact_name' => fake()->name(),
            'contact_persons' => null,
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => null,
            'fax' => null,
            'homepage' => 'https://example.com',
            'address' => null,
            'address_street' => fake()->streetAddress(),
            'address_zip' => fake()->postcode(),
            'address_city' => fake()->city(),
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'color' => '#3b82f6',
            'hourly_rate' => 95.00,
            'internal_rate' => null,
            'comment' => null,
            'invoice_text' => null,
            'billable' => true,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function archived(): static {
        return $this->state(['archived_at' => now()]);
    }

    public function notBillable(): static {
        return $this->state(['billable' => false]);
    }
}
