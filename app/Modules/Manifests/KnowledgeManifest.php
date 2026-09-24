<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Wissensbasis“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class KnowledgeManifest extends Manifest {
    public function code(): string {
        return 'knowledge';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Wissensbasis';
    }

    public function licenseCode(): string {
        return 'module.knowledge';
    }

    public function description(): string {
        return 'Wissensbasis und Problemhistorie.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Knowledge',
            'Content',
            'Collections',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'collection_items',
            'collections',
            'content_references',
            'knowledge_article_feedback',
            'knowledge_articles',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'knowledge.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Knowledge,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'knowledge.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Navigation\Contracts\NavigationCondition::class => [
                \App\Services\Collections\Navigation\KnowledgeHubCondition::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Knowledge\Search\KnowledgeArticleSource::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array {
        return [
            \App\Services\Search\Contracts\CollectionScope::class => \App\Services\Collections\Search\CollectionSearchScope::class,
            \App\Services\ServiceTicket\Contracts\KnownErrorPublisher::class => \App\Services\Knowledge\Helpdesk\KnownErrorArticlePublisher::class,
        ];
    }
}
