<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Time;

use App\Enums\Attendance\PresenceStatusKind;
use App\Models\Platform\{Organization, User};
use App\Models\Time\PresenceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresenceStatus>
 */
class PresenceStatusFactory extends Factory {
    protected $model = PresenceStatus::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'kind' => PresenceStatusKind::DoNotDisturb,
            'valid_until' => null,
        ];
    }
}
