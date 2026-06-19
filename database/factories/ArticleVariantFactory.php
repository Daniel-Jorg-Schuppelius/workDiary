<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVariantFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Article\ArticleStatus;
use App\Models\{Article, ArticleVariant};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleVariant>
 */
class ArticleVariantFactory extends Factory {
    protected $model = ArticleVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'article_id' => Article::factory(),
            'sku' => null,
            'status' => ArticleStatus::Active->value,
            'is_default' => false,
            'option_signature' => '',
            'currency' => 'EUR',
        ];
    }
}
