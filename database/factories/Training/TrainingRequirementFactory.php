<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Training;

use App\Enums\Training\TrainingRequirementSubject;
use App\Enums\User\UserRole;
use App\Models\Training\TrainingRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingRequirement>
 */
class TrainingRequirementFactory extends Factory {
    protected $model = TrainingRequirement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
            'first_due_days' => 30,
            'is_active' => true,
            'source' => 'manual',
            'note' => null,
        ];
    }
}
