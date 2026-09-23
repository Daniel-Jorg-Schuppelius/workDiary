<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceCheckpointFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Attendance\CheckpointKind;
use App\Models\AttendanceCheckpoint;
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCheckpoint>
 */
class AttendanceCheckpointFactory extends Factory {
    protected $model = AttendanceCheckpoint::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Halle ' . fake()->numberBetween(1, 9),
            'kind' => CheckpointKind::Site->value,
            'token' => AttendanceCheckpoint::newToken(),
            'active' => true,
        ];
    }
}
