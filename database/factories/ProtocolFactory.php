<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Protocol\{ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Models\{DiaryEntry, Protocol, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Protocol>
 */
class ProtocolFactory extends Factory {
    protected $model = Protocol::class;

    public function definition(): array {
        return [
            'type' => ProtocolType::Service->value,
            'subject_type' => DiaryEntry::class,
            'subject_id' => DiaryEntry::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'state_initial' => null,
            'state_final' => null,
            'status' => ProtocolStatus::Draft->value,
            'revision' => 1,
            'supersedes_id' => null,
            'visibility' => ProtocolVisibility::Internal->value,
            'occurred_at' => now(),
            'created_by_user_id' => User::factory(),
            'signed_at' => null,
            'archived_at' => null,
        ];
    }

    public function inReview(): self {
        return $this->state(fn() => ['status' => ProtocolStatus::InReview->value]);
    }

    public function signed(): self {
        return $this->state(fn() => [
            'status' => ProtocolStatus::Signed->value,
            'signed_at' => now(),
        ]);
    }

    public function customerVisible(): self {
        return $this->state(fn() => ['visibility' => ProtocolVisibility::Customer->value]);
    }
}
