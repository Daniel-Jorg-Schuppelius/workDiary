<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Learning;

use App\Enums\Learning\{LearningAccessKind, LearningAudience, LearningCourseStatus, LearningInstructionSuitability, LearningTimePolicy};
use App\Models\Learning\LearningCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCourse>
 */
class LearningCourseFactory extends Factory {
    protected $model = LearningCourse::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'code' => 'lernkurs-' . fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->randomElement(['Brandschutz kompakt', 'Ladungssicherung', 'Hygiene im Alltag', 'PSA gegen Absturz', 'Datenschutz-Basiswissen']),
            'subtitle' => null,
            'description' => null,
            'objectives' => null,
            'language' => 'de',
            'status' => LearningCourseStatus::Draft->value,
            'audiences' => [LearningAudience::Internal->value],
            'access_kind' => LearningAccessKind::Enrolled->value,
            'training_course_id' => null,
            'article_id' => null,
            'owner_user_id' => null,
            'duration_minutes' => 45,
            'validity_months' => 12,
            'points' => 0,
            'time_policy' => LearningTimePolicy::WorkTimeRequired->value,
            'instruction_suitability' => LearningInstructionSuitability::Supplementary->value,
            'certificate_enabled' => false,
            'access_days' => null,
            'sequential' => false,
        ];
    }
}
