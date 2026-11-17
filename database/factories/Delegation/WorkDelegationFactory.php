<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\{Organization, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Wirksame Vertretung von gestern bis in einer Woche; Personen erfunden.
 *
 * @extends Factory<WorkDelegation>
 */
class WorkDelegationFactory extends Factory {
    protected $model = WorkDelegation::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'represented_user_id' => User::factory(),
            'deputy_user_id' => User::factory(),
            'valid_from' => now()->subDay()->startOfDay(),
            'valid_until' => now()->addWeek()->startOfDay(),
            'kind' => WorkDelegationKind::Other,
        ];
    }
}
