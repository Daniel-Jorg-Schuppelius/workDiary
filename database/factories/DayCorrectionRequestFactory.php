<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCorrectionRequestFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\TimeApproval\DayCorrectionStatus;
use App\Models\{DayClosure, DayCorrectionRequest, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayCorrectionRequest>
 */
class DayCorrectionRequestFactory extends Factory {
    protected $model = DayCorrectionRequest::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'day_closure_id' => DayClosure::factory(),
            'requested_by_user_id' => User::factory(),
            'reason' => fake()->sentence(10),
            'status' => DayCorrectionStatus::Pending->value,
        ];
    }
}
