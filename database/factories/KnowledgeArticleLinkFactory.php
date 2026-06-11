<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleLinkFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{KnowledgeArticle, KnowledgeArticleLink, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeArticleLink>
 */
class KnowledgeArticleLinkFactory extends Factory {
    protected $model = KnowledgeArticleLink::class;

    public function definition(): array {
        return [
            'knowledge_article_id' => KnowledgeArticle::factory(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
