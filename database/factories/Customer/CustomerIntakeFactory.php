<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Customer;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Models\Customer\{Customer, CustomerIntake};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerIntake>
 */
class CustomerIntakeFactory extends Factory {
    protected $model = CustomerIntake::class;

    public function definition(): array {
        return [
            'customer_id' => Customer::factory(),
            'number' => 'KE-' . now()->year . '-' . fake()->unique()->numerify('####'),
            'kind' => IntakeKind::Print,
            'status' => IntakeStatus::Submitted,
            'subject' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'submission_key' => (string) Str::uuid(),
        ];
    }

    /** Organisation des Kunden übernehmen, damit Eingang und Kunde nie auseinanderlaufen. */
    public function configure(): static {
        return $this->afterMaking(function (CustomerIntake $intake): void {
            if (empty($intake->organization_id)) {
                $intake->organization_id = (int) Customer::query()->withoutGlobalScopes()->whereKey($intake->customer_id)->value('organization_id');
            }
        });
    }
}
