<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleUnitFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Article\ArticleUnitKind;
use App\Models\{Article, ArticleUnit};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleUnit>
 */
class ArticleUnitFactory extends Factory {
    protected $model = ArticleUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'article_id' => Article::factory(),
            'code' => fake()->unique()->lexify('unit_???'),
            'label' => ucfirst(fake()->word()),
            'kind' => ArticleUnitKind::Packaging->value,
            'factor_to_base' => '1',
            'active' => true,
        ];
    }
}
