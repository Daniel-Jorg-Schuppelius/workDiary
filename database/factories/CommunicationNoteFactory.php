<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility};
use App\Models\{CommunicationNote, DiaryEntry, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationNote>
 */
class CommunicationNoteFactory extends Factory {
    protected $model = CommunicationNote::class;

    public function definition(): array {
        return [
            'notable_type' => DiaryEntry::class,
            'notable_id' => DiaryEntry::factory(),
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Outbound->value,
            'occurred_at' => now()->subHour(),
            'subject' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'result' => null,
            'next_action' => null,
            'next_action_due_at' => null,
            'next_action_user_id' => null,
            'next_action_completed_at' => null,
            'next_action_completed_by_user_id' => null,
            'visibility' => CommunicationVisibility::Internal->value,
            'confidential' => false,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function confidential(): self {
        return $this->state(fn() => [
            'confidential' => true,
            'visibility' => CommunicationVisibility::Internal->value,
        ]);
    }

    public function customerVisible(): self {
        return $this->state(fn() => [
            'visibility' => CommunicationVisibility::Customer->value,
        ]);
    }

    public function internal(): self {
        return $this->state(fn() => [
            'type' => CommunicationNoteType::Internal->value,
            'direction' => CommunicationDirection::Internal->value,
            'visibility' => CommunicationVisibility::Internal->value,
        ]);
    }

    public function withFollowUp(): self {
        return $this->state(fn(array $attributes) => [
            'next_action' => 'Angebot bis Freitag versenden',
            'next_action_due_at' => now()->addDays(3),
        ]);
    }
}
