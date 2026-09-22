<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Club;

use App\Enums\Club\ClubMembershipKind;
use App\Models\Club\ClubMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory {
    protected $model = ClubMember::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'member_no' => fake()->unique()->numberBetween(1, 100000),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional(0.6)->safeEmail(),
            'phone' => null,
            'street' => fake()->optional(0.5)->streetAddress(),
            'postal_code' => fake()->optional(0.5)->postcode(),
            'city' => fake()->optional(0.5)->city(),
            'birth_date' => CarbonImmutable::today()->subYears(fake()->numberBetween(8, 60))->subDays(fake()->numberBetween(0, 364))->toDateString(),
            'kind' => ClubMembershipKind::Active->value,
            'joined_on' => CarbonImmutable::today()->subDays(fake()->numberBetween(30, 2000))->toDateString(),
            'left_on' => null,
            'user_id' => null,
            'notes' => null,
            'created_by_user_id' => null,
        ];
    }

    /** Mitglied, das am heutigen Tag genau $years Jahre alt ist (Geburtstag heute). */
    public function birthdayToday(int $years): self {
        return $this->state(['birth_date' => CarbonImmutable::today()->subYears($years)->toDateString()]);
    }

    /** Mitglied mit $years vollen Jahren (Geburtstag liegt einen Tag zurück). */
    public function aged(int $years): self {
        return $this->state(['birth_date' => CarbonImmutable::today()->subYears($years)->subDay()->toDateString()]);
    }

    public function withoutBirthDate(): self {
        return $this->state(['birth_date' => null]);
    }

    public function left(?string $on = null): self {
        return $this->state(['left_on' => $on ?? CarbonImmutable::yesterday()->toDateString()]);
    }
}
