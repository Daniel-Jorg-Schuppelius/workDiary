<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteParticipantFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Communication\ParticipantParty;
use App\Models\{CommunicationNote, CommunicationNoteParticipant};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationNoteParticipant>
 */
class CommunicationNoteParticipantFactory extends Factory {
    protected $model = CommunicationNoteParticipant::class;

    public function definition(): array {
        return [
            'communication_note_id' => CommunicationNote::factory(),
            'user_id' => null,
            'customer_contact_id' => null,
            'name' => fake()->name(),
            'role' => null,
            'party' => ParticipantParty::Customer->value,
        ];
    }

    public function internal(): self {
        return $this->state(fn() => [
            'party' => ParticipantParty::Internal->value,
        ]);
    }

    public function thirdParty(): self {
        return $this->state(fn() => [
            'party' => ParticipantParty::ThirdParty->value,
        ]);
    }
}
