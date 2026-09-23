<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Customer, ForeignCustomer, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForeignCustomer>
 */
class ForeignCustomerFactory extends Factory {
    protected $model = ForeignCustomer::class;

    public function definition(): array {
        $company = fake()->company();

        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'customer_id' => Customer::factory(),
            'name' => $company,
            'number' => null,
            'company' => $company,
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => null,
            'homepage' => 'https://example.com',
            'address' => null,
            'country' => 'DE',
            'color' => '#64748b',
            'comment' => null,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function archived(): static {
        return $this->state(['archived_at' => now()]);
    }
}
