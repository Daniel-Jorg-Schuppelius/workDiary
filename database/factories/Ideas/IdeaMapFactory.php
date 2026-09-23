<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{IdeaMap, Organization, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaMap>
 */
class IdeaMapFactory extends Factory {
    protected $model = IdeaMap::class;

    public function definition(): array {
        $owner = User::factory();

        return [
            'organization_id' => Organization::factory(),
            'created_by' => $owner,
            'owner_user_id' => $owner,
            'title' => $this->faker->sentence(3),
            'description' => null,
            'visibility' => 'private',
        ];
    }
}
