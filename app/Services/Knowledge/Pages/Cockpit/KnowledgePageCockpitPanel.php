<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Cockpit;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Knowledge\Pages\KnowledgePageLinks;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Verknüpfte Wissensseiten im Cockpit von Kunde, Projekt und Asset (Feature
 * 182/191, MVP-1667) — „Was bei Kunde X zu beachten ist“.
 */
final class KnowledgePageCockpitPanel implements CockpitPanel {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly KnowledgePageLinks $links,
    ) {}

    public function order(): int {
        return 60;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer || $subject instanceof Project || $subject instanceof Asset;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $this->features->isEnabled('module.knowledge') || ! Gate::forUser($viewer)->allows('viewAny', KnowledgePage::class)) {
            return null;
        }
        $pages = $this->links->pagesFor($subject, $viewer);
        if ($pages->isEmpty()) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-knowledge-pages',
            'title' => __('knowledge_pages.cockpit.title'),
            'icon' => 'auto_stories',
            'count' => $pages->count(),
            'rows' => array_map(static fn (KnowledgePage $page): array => [
                'title' => $page->title,
                'url' => route('knowledge.pages.show', $page),
                'subtitle' => $page->space?->title,
                'badge' => $page->status->label(),
                'tone' => $page->status->tone(),
                'date' => $page->updated_at !== null ? CarbonFmt::fdate($page->updated_at) : null,
            ], $pages->all()),
        ]);
    }
}
