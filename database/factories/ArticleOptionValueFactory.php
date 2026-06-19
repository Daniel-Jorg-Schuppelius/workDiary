<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleOptionValueFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{ArticleOptionDefinition, ArticleOptionValue};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleOptionValue>
 */
class ArticleOptionValueFactory extends Factory {
    protected $model = ArticleOptionValue::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'article_option_definition_id' => ArticleOptionDefinition::factory(),
            'code' => fake()->unique()->lexify('val_????'),
            'label' => ucfirst(fake()->word()),
            'position' => 0,
            'active' => true,
        ];
    }
}
