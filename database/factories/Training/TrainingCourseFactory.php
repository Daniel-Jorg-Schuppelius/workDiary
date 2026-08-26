<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCourseFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Training;

use App\Enums\Training\TrainingProviderKind;
use App\Models\Training\TrainingCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCourse>
 */
class TrainingCourseFactory extends Factory {
    protected $model = TrainingCourse::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'code' => 'kurs-' . fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->randomElement(['Brandschutzhelfer', 'Erste Hilfe', 'Ladungssicherung', 'PSA gegen Absturz', 'Hygieneschulung']),
            'provider_kind' => TrainingProviderKind::Internal->value,
            'provider_name' => null,
            'duration_minutes' => 120,
            'validity_months' => 12,
            'is_mandatory' => true,
            'legal_basis' => '§ 12 ArbSchG',
            'cost_amount' => null,
            'cost_currency' => null,
            'lead_days' => 30,
            'notes' => null,
            'is_active' => true,
            'source' => 'manual',
            'created_by_user_id' => null,
        ];
    }
}
