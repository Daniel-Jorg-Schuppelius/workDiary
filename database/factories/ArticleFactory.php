<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory {
    protected $model = Article::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // explizit/Scope setzen
            'number' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => ArticleType::Consumable->value,
            'base_unit' => 'Stk',
            'status' => ArticleStatus::Active->value,
            'currency' => 'EUR',
        ];
    }

    public function draft(): static {
        return $this->state(fn() => ['status' => ArticleStatus::Draft->value]);
    }

    public function manufacturable(): static {
        return $this->state(fn() => [
            'type' => ArticleType::Finished->value,
            'manufacturable' => true,
        ]);
    }
}
