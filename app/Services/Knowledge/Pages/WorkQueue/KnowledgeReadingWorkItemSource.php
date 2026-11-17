<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeReadingWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\WorkQueue;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\{Organization, User};
use App\Services\Knowledge\Pages\KnowledgeReadingService;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Pflichtlektüre im Arbeitsvorrat (Feature 191, MVP-1666): je Seite,
 * deren freigegebene Fassung die Person noch nicht bestätigt hat — die
 * Einarbeitungsliste zuerst.
 */
final class KnowledgeReadingWorkItemSource implements WorkItemSource {
    public const string KEY = 'knowledge_reading';

    public function __construct(private readonly KnowledgeReadingService $readings) {}

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('knowledge_pages.work_queue.reading');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', KnowledgePage::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        foreach ($this->readings->openFor($viewer) as $row) {
            $page = $row['page'];
            yield new WorkItem(
                source: $this->key(),
                key: $page->getMorphClass() . ':' . $page->id . ':' . $page->approved_version,
                title: (string) __('knowledge_pages.work_queue.reading_item', ['title' => $page->title]),
                url: route('knowledge.pages.show', $page),
                detail: $row['onboarding'] ? (string) __('knowledge_pages.reading.onboarding') : $page->space?->title,
                personal: true,
                rank: $row['onboarding'] ? 0 : 1,
            );
        }
    }
}
