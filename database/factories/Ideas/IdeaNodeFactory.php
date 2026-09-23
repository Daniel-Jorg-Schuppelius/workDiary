<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{IdeaMap, IdeaNode, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaNode>
 */
class IdeaNodeFactory extends Factory {
    protected $model = IdeaNode::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'idea_map_id' => IdeaMap::factory(),
            'parent_id' => null,
            'is_root' => false,
            'title' => $this->faker->sentence(2),
            'sort_order' => 0,
            'lock_version' => 1,
        ];
    }

    public function root(): static {
        return $this->state(fn (): array => ['is_root' => true, 'parent_id' => null]);
    }
}
