<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Knowledge\{ArticleStatus, ArticleVisibility};
use App\Models\{KnowledgeArticle, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory {
    protected $model = KnowledgeArticle::class;

    public function definition(): array {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 999999),
            'problem' => fake()->paragraph(),
            'solution' => fake()->paragraphs(2, true),
            'category' => null,
            'status' => ArticleStatus::Draft->value,
            'visibility' => ArticleVisibility::Internal->value,
            'created_by_user_id' => User::factory(),
            'published_at' => null,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
        ];
    }

    public function published(): self {
        return $this->state(fn() => [
            'status' => ArticleStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function archived(): self {
        return $this->state(fn() => [
            'status' => ArticleStatus::Archived->value,
        ]);
    }
}
