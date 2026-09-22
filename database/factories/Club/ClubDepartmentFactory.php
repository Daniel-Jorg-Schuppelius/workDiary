<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDepartmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Club;

use App\Models\Club\ClubDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubDepartment>
 */
class ClubDepartmentFactory extends Factory {
    protected $model = ClubDepartment::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'name' => 'Abteilung ' . fake()->unique()->numberBetween(1, 100000),
            'description' => null,
            'discipline' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
