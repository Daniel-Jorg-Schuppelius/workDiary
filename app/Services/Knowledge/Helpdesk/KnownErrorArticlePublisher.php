<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnownErrorArticlePublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Helpdesk;

use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Platform\User;
use App\Models\ServiceTicket\Problem;
use App\Services\Collections\ContentCollectionService;
use App\Services\Knowledge\KnowledgeArticleService;
use App\Services\ServiceTicket\Contracts\KnownErrorPublisher;

/** Known-Error-Artikel aus einem Problem; seit MVP-814 in der Sammlung „Known Errors". */
final class KnownErrorArticlePublisher implements KnownErrorPublisher {
    public function __construct(
        private readonly KnowledgeArticleService $articles,
        private readonly ContentCollectionService $collections,
    ) {}

    public function publish(Problem $problem, User $actor): KnowledgeArticle {
        $article = $this->articles->create($actor, [
            'title' => (string) __('Known Error: :title', ['title' => $problem->title]),
            'problem' => (string) ($problem->description ?? $problem->title),
            'solution' => trim((string) ($problem->workaround ?? '') . "\n\n" . (string) ($problem->permanent_fix ?? '')),
        ]);
        $this->collections->placeInNamedCollection((int) $article->organization_id, 'Known Errors', $article, $actor);

        return $article;
    }
}
