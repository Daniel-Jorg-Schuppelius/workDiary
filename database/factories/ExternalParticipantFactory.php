<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\ExternalParticipant\{ExternalAbility, ExternalParty};
use App\Models\{DiaryEntry, ExternalParticipant, Organization, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\{Carbon, Str};

/**
 * @extends Factory<ExternalParticipant>
 */
class ExternalParticipantFactory extends Factory {
    protected $model = ExternalParticipant::class;

    public function definition(): array {
        $token = Str::random(48);

        return [
            'organization_id' => Organization::factory(),
            'subject_type' => DiaryEntry::class,
            'subject_id' => DiaryEntry::factory(),
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'role' => fake()->randomElement(['Subunternehmer', 'Prüfer', 'Sachverständiger']),
            'party' => fake()->randomElement(ExternalParty::cases())->value,
            'token_hash' => hash('sha256', $token),
            'abilities' => [ExternalAbility::Comment->value],
            'expires_at' => Carbon::now()->addDays(14),
            'invited_by_user_id' => User::factory(),
            'accepted_at' => null,
            'last_access_at' => null,
            'revoked_at' => null,
            'created_at' => Carbon::now(),
        ];
    }

    /** Setzt einen bekannten Klartext-Token (für Tests, die den Link aufrufen). */
    public function withPlainToken(string $plain): static {
        return $this->state(fn(): array => ['token_hash' => hash('sha256', $plain)]);
    }

    /** @param list<string> $abilities */
    public function abilities(array $abilities): static {
        return $this->state(fn(): array => ['abilities' => $abilities]);
    }

    public function viewOnly(): static {
        return $this->state(fn(): array => ['abilities' => []]);
    }

    public function expired(): static {
        return $this->state(fn(): array => ['expires_at' => Carbon::now()->subDay()]);
    }

    public function revoked(): static {
        return $this->state(fn(): array => ['revoked_at' => Carbon::now()]);
    }
}
