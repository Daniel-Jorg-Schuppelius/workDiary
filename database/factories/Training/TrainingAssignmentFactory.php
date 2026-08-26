<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Training;

use App\Models\Training\TrainingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingAssignment>
 */
class TrainingAssignmentFactory extends Factory {
    protected $model = TrainingAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $due = now()->addDays(45)->toDateString();

        return [
            'training_requirement_id' => null,
            'source' => 'manual',
            'due_at' => $due,
            'notify_from' => now()->addDays(15)->toDateString(),
            'fulfilled_at' => null,
            'fulfilled_participant_id' => null,
            'fulfilled_instruction_id' => null,
            'fulfilled_course_version' => null,
        ];
    }

    /** Überfälliger Soll-Eintrag (Termin überschritten). */
    public function overdue(): self {
        return $this->state(fn(): array => [
            'due_at' => now()->subDays(5)->toDateString(),
            'notify_from' => now()->subDays(35)->toDateString(),
        ]);
    }
}
