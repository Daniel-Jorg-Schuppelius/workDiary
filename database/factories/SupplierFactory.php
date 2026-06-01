<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Supplier, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory {
    protected $model = Supplier::class;

    public function definition(): array {
        $company = fake()->company();

        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'name' => $company,
            'number' => null, // Auto-Nummer via Supplier::booted()
            'vendor_number' => null,
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
            'comment' => null,
            'active' => true,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function archived(): static {
        return $this->state(['archived_at' => now()]);
    }

    public function inactive(): static {
        return $this->state(['active' => false]);
    }
}
