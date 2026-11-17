<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Knowledge;

use App\Enums\Knowledge\{KnowledgePageKind, KnowledgePageStatus};
use App\Models\Knowledge\{KnowledgePage, KnowledgeSpace};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Seite ohne Fassungszeile — Tests, die Fassungen brauchen, legen die Seite
 * über den KnowledgePageService an.
 *
 * @extends Factory<KnowledgePage>
 */
class KnowledgePageFactory extends Factory {
    protected $model = KnowledgePage::class;

    public function definition(): array {
        return [
            'knowledge_space_id' => KnowledgeSpace::factory(),
            'kind' => KnowledgePageKind::Page->value,
            'position' => 0,
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'status' => KnowledgePageStatus::Draft->value,
            'version' => 1,
            'approved_version' => null,
            'is_emergency' => false,
        ];
    }
}
