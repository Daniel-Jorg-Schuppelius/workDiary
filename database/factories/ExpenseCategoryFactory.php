<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategoryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory {
    protected $model = ExpenseCategory::class;

    public function definition(): array {
        $label = fake()->unique()->randomElement([
            'Verpflegung', 'Übernachtung', 'Bewirtung', 'Tickets',
            'Parking', 'Material', 'Telekommunikation', 'Sonstiges',
        ]) . ' ' . fake()->unique()->numberBetween(1, 9999);

        return [
            'organization_id' => null,
            'slug' => Str::slug($label),
            'label' => $label,
            'icon' => 'receipt_long',
            'color' => 'primary',
            'description' => null,
            'default_tax_rate' => '19.00',
            'default_billable' => false,
            'requires_receipt' => false,
            'sort' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
