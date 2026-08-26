<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionParticipantFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Safety;

use App\Enums\Safety\InstructionSignatureMethod;
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyInstructionParticipant>
 */
class SafetyInstructionParticipantFactory extends Factory {
    protected $model = SafetyInstructionParticipant::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'safety_instruction_id' => SafetyInstruction::factory(),
            'user_id' => User::factory(),
            'signer_name' => null,
            'signed_at' => null,
            'method' => null,
            'signature_image_path' => null,
            'ip' => null,
            'hash' => null,
            'next_due_on' => null,
        ];
    }

    public function signed(): self {
        return $this->state(fn() => [
            'signer_name' => fake()->name(),
            'signed_at' => now(),
            'method' => InstructionSignatureMethod::Confirmed->value,
            'ip' => '127.0.0.1',
            'hash' => hash('sha256', fake()->uuid()),
        ]);
    }
}
