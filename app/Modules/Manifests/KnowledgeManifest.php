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
        return 'Wissensbasis und Problemhistorie, interne Wissensdatenbank mit Fassungen, Pflichtlektüre und Notfallhandbuch.';
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
            // Interne Wissensdatenbank (Feature 191, MVP-1664–1668).
            'knowledge_glossary_terms',
            'knowledge_guide_steps',
            'knowledge_page_assignments',
            'knowledge_page_reads',
            'knowledge_page_versions',
            'knowledge_page_visits',
            'knowledge_pages',
            'knowledge_search_gaps',
            'knowledge_sections',
            'knowledge_space_readers',
            'knowledge_spaces',
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
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Knowledge\Demo\KnowledgeDemoBlock::class,
                \App\Services\Knowledge\Pages\Demo\KnowledgePagesDemoBlock::class,
            ],
            // Interne Wissensdatenbank (Feature 191): globale Suche, Arbeitsvorrat, Cockpit, Prüfintervall.
            \App\Services\Search\Contracts\SearchDomain::class => [
                \App\Services\Knowledge\Pages\Search\KnowledgePageSearchDomain::class,
            ],
            \App\Services\WorkQueue\Contracts\WorkItemSource::class => [
                \App\Services\Knowledge\Pages\WorkQueue\KnowledgeReadingWorkItemSource::class,
                \App\Services\Knowledge\Pages\WorkQueue\KnowledgeReviewWorkItemSource::class,
            ],
            \App\Services\Cockpit\Contracts\CockpitPanel::class => [
                \App\Services\Knowledge\Pages\Cockpit\KnowledgePageCockpitPanel::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Knowledge\Pages\DeadlineScans\KnowledgePageReviewScan::class,
            ],
            // Seitenverantwortung im Austritts- und Übergabe-Dialog (Feature 191/202).
            \App\Services\Org\Contracts\OffboardingHandover::class => [
                \App\Services\Knowledge\Pages\Offboarding\KnowledgePageOffboardingHandover::class,
            ],
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
