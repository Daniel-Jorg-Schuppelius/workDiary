<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Club;

use App\Enums\Club\ClubAdmissionMode;
use App\Models\Club\ClubGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubGroup>
 */
class ClubGroupFactory extends Factory {
    protected $model = ClubGroup::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'club_department_id' => null,
            'name' => 'Gruppe ' . fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->optional()->sentence(),
            'leader_user_id' => null,
            'max_members' => null,
            'admission_mode' => ClubAdmissionMode::Leader->value,
            'min_age' => null,
            'max_age' => null,
            'criteria_note' => null,
            'is_active' => true,
        ];
    }

    public function ageRange(?int $min, ?int $max): self {
        return $this->state(['min_age' => $min, 'max_age' => $max]);
    }

    public function capacity(int $maxMembers): self {
        return $this->state(['max_members' => $maxMembers]);
    }

    public function byApplication(): self {
        return $this->state(['admission_mode' => ClubAdmissionMode::Application->value]);
    }
}
