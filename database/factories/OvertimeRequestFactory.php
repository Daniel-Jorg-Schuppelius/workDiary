<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Models\{OvertimeRequest, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OvertimeRequest> */
class OvertimeRequestFactory extends Factory {
    protected $model = OvertimeRequest::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'user_id' => User::factory(),
            'requested_by_user_id' => fn (array $attrs) => $attrs['user_id'],
            'scope_date' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'minutes' => fake()->numberBetween(15, 180),
            'reason' => fake()->sentence(12),
            'status' => OvertimeRequestStatus::Submitted->value,
        ];
    }
}
