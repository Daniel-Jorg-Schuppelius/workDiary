<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCourseVersionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Training;

use App\Models\Training\TrainingCourseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCourseVersion>
 */
class TrainingCourseVersionFactory extends Factory {
    protected $model = TrainingCourseVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'version' => 1,
            'label' => null,
            'valid_from' => null,
            'content_summary' => null,
            'is_current' => true,
        ];
    }
}
