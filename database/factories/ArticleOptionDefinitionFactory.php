<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleOptionDefinitionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Article, ArticleOptionDefinition};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleOptionDefinition>
 */
class ArticleOptionDefinitionFactory extends Factory {
    protected $model = ArticleOptionDefinition::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'article_id' => Article::factory(),
            'code' => fake()->unique()->lexify('opt_????'),
            'name' => ucfirst(fake()->word()),
            'position' => 0,
            'active' => true,
        ];
    }
}
