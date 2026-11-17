<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePagesDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Demo;

use App\Enums\Knowledge\KnowledgeAudienceKind;
use App\Models\Knowledge\{KnowledgeGlossaryTerm, KnowledgePage, KnowledgeSearchGap, KnowledgeSection, KnowledgeSpace};
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Knowledge\Pages\{KnowledgePageService, KnowledgeReadingService};

/**
 * Wissensseiten zur Vorführung (Feature 191): ein Handbuch mit erfundenen
 * Inhalten — Arbeitsplatz einrichten (Pflichtlektüre der Einarbeitung),
 * Vorgehen bei Serverausfall (Notfallhandbuch), eine Entscheidung als
 * Entwurf, dazu Glossar und eine Suchlücke.
 */
final class KnowledgePagesDemoBlock implements DemoBlock {
    public function __construct(
        private readonly KnowledgePageService $pages,
        private readonly KnowledgeReadingService $readings,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        if (! $context->moduleActive('module.knowledge') || $admin === null) {
            return ['knowledge_pages' => 0];
        }
        $organization = $context->organization;

        $space = KnowledgeSpace::query()->create([
            'organization_id' => $organization->id,
            'title' => (string) __('knowledge_pages.demo.space'),
            'description' => (string) __('knowledge_pages.demo.space_description'),
            'position' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $section = KnowledgeSection::query()->create([
            'organization_id' => $organization->id,
            'knowledge_space_id' => $space->id,
            'title' => (string) __('knowledge_pages.demo.section'),
            'position' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $workplace = $this->pages->create($space, $section, null, $admin, [
            'title' => (string) __('knowledge_pages.demo.workplace_title'),
            'body' => (string) __('knowledge_pages.demo.workplace_body'),
            'tags' => (string) __('knowledge_pages.demo.workplace_tags'),
        ]);
        $this->pages->approve($workplace, $admin);
        $this->readings->assign($workplace, $admin, KnowledgeAudienceKind::All, null, null, null, true);

        $emergency = $this->pages->create($space, $section, null, $admin, [
            'title' => (string) __('knowledge_pages.demo.emergency_title'),
            'body' => (string) __('knowledge_pages.demo.emergency_body'),
        ]);
        $emergency->forceFill(['is_emergency' => true, 'review_interval_months' => 6])->save();
        $this->pages->approve($emergency, $admin);

        $this->pages->create($space, $section, null, $admin, [
            'title' => (string) __('knowledge_pages.demo.decision_title'),
            'body' => (string) __('knowledge_pages.demo.decision_body'),
        ]);

        KnowledgeGlossaryTerm::query()->create([
            'organization_id' => $organization->id,
            'term' => (string) __('knowledge_pages.demo.glossary_term'),
            'synonyms' => [(string) __('knowledge_pages.demo.glossary_synonym')],
            'description' => (string) __('knowledge_pages.demo.glossary_description'),
            'knowledge_page_id' => $emergency->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        KnowledgeSearchGap::query()->create([
            'organization_id' => $organization->id,
            'term' => (string) __('knowledge_pages.demo.gap_term'),
            'searches' => 3,
            'first_searched_at' => now()->subDays(5),
            'last_searched_at' => now()->subDay(),
        ]);

        return ['knowledge_pages' => 3];
    }

    public function purge(Organization $organization): void {
        KnowledgePage::query()->withoutGlobalScopes()->withTrashed()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->each(fn (KnowledgePage $page) => $this->pages->forceDelete($page, null));
        KnowledgeGlossaryTerm::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        KnowledgeSearchGap::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        KnowledgeSpace::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
