<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Demo;

use App\Models\Knowledge\{ContentReference, KnowledgeArticle, KnowledgeArticleFeedback};
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Knowledge\KnowledgeArticleService;
use App\Support\MorphMap;

/**
 * Wissensbasis zur Vorführung (MVP-1532): zwei veröffentlichte Artikel aus dem
 * Hauptfall der Musterbranche (Abnahme-Leitfaden am Auftrag verknüpft, offener
 * Punkt als Problem/Lösung) mit Bewertungen, dazu ein Entwurf.
 */
final class KnowledgeDemoBlock implements DemoBlock {
    public function __construct(private readonly KnowledgeArticleService $articles) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        if (! $context->moduleActive('module.knowledge') || $admin === null) {
            return ['knowledge_articles' => 0];
        }

        /** @var array{content: string, open_issue_title: string, open_issue_desc: string, protocol_title: string, protocol_items: list<array{label: string}>} $main */
        $main = $context->blueprint['main_case'];
        $steps = [];
        foreach ($main['protocol_items'] as $index => $item) {
            $steps[] = ($index + 1) . '. ' . $item['label'];
        }

        $guide = $this->articles->create($admin, [
            'title' => (string) __('demo.knowledge.guide_title', ['subject' => $main['protocol_title']]),
            'problem' => $main['content'],
            'solution' => (string) __('demo.knowledge.guide_solution') . "\n\n" . implode("\n", $steps),
        ]);
        $this->articles->publish($guide, $admin);
        if ($context->mainDiary !== null) {
            $this->articles->linkTo($guide, $context->mainDiary, $admin);
        }

        $issue = $this->articles->create($admin, [
            'title' => $main['open_issue_title'],
            'problem' => $main['open_issue_desc'],
            'solution' => (string) __('demo.knowledge.issue_solution'),
        ]);
        $this->articles->publish($issue, $admin);

        $this->articles->create($admin, [
            'title' => (string) __('demo.knowledge.onboarding_title'),
            'problem' => (string) __('demo.knowledge.onboarding_problem'),
            'solution' => (string) __('demo.knowledge.onboarding_solution'),
        ]);

        foreach ($context->users->slice(1, 2) as $reader) {
            $this->articles->feedback($guide, $reader, true);
        }
        $doubter = $context->users->get(3);
        if ($doubter !== null) {
            $this->articles->feedback($issue, $doubter, false);
        }

        return ['knowledge_articles' => 3];
    }

    public function purge(Organization $organization): void {
        $articleIds = KnowledgeArticle::query()->withoutGlobalScopes()->withTrashed()->where('organization_id', $organization->id)->pluck('id');
        ContentReference::query()->withoutGlobalScopes()
            ->where('source_type', MorphMap::alias(KnowledgeArticle::class))
            ->whereIn('source_id', $articleIds)
            ->delete();
        KnowledgeArticleFeedback::query()->whereIn('knowledge_article_id', $articleIds)->delete();
        KnowledgeArticle::query()->withoutGlobalScopes()->withTrashed()->whereKey($articleIds)->forceDelete();
    }
}
