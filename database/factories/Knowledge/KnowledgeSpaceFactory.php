<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSpaceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Knowledge;

use App\Models\Knowledge\KnowledgeSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSpace>
 */
class KnowledgeSpaceFactory extends Factory {
    protected $model = KnowledgeSpace::class;

    public function definition(): array {
        return [
            'title' => fake()->words(2, true),
            'description' => null,
            'position' => 0,
            'is_restricted' => false,
        ];
    }

    public function restricted(): self {
        return $this->state(fn () => ['is_restricted' => true]);
    }
}
