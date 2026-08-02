<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Disposal;

use App\Enums\Disposal\DisposalJobStatus;
use App\Models\{Customer, Organization, User};
use App\Models\Disposal\DisposalJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisposalJob> */
class DisposalJobFactory extends Factory {
    protected $model = DisposalJob::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'number' => 'ENT-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => DisposalJobStatus::Draft->value,
            'customer_id' => Customer::factory(),
            'picked_up_on' => now()->toDateString(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
