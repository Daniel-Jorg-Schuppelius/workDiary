<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Expense\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory {
    protected $model = Expense::class;

    public function definition(): array {
        $net = fake()->randomFloat(2, 5, 500);
        $rate = 19.0;
        $tax = round($net * $rate / 100, 2);

        return [
            'organization_id' => null,
            'user_id' => User::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'project_id' => null,
            'customer_id' => null,
            'task_id' => null,
            'attendance_id' => null,
            'date' => Carbon::instance(fake()->dateTimeBetween('-30 days', 'now'))->startOfDay(),
            'vendor' => fake()->company(),
            'description' => fake()->sentence(4),
            'payment_method' => PaymentMethod::PrivatePaid->value,
            'currency' => 'EUR',
            'amount_net' => (string) $net,
            'tax_rate' => (string) $rate,
            'tax_amount' => (string) $tax,
            'amount_gross' => (string) round($net + $tax, 2),
            'billable' => false,
            'status' => ExpenseStatus::Draft->value,
        ];
    }

    public function pending(): self {
        return $this->state(['status' => ExpenseStatus::Pending->value]);
    }

    public function approved(): self {
        return $this->state(fn() => [
            'status' => ExpenseStatus::Approved->value,
            'decided_at' => now(),
        ]);
    }

    public function billable(): self {
        return $this->state(['billable' => true]);
    }
}
