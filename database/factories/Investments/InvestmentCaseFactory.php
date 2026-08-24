<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCaseFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Investments;

use App\Models\Investments\InvestmentCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentCase>
 */
class InvestmentCaseFactory extends Factory {
    protected $model = InvestmentCase::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'title' => 'Ersatzbeschaffung ' . fake()->words(2, true),
            'category' => 'machine',
            'urgency' => 'medium',
            'status' => 'idea',
            'created_by' => null,
        ];
    }
}
