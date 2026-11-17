<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeReviewWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\WorkQueue;

use App\Enums\Knowledge\KnowledgePageStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\{Organization, User};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Aufgaben der verantwortlichen Person (Feature 191, MVP-1665): Prüfung fällig
 * („Stimmt, aktuell“), Gültigkeit endet bald, neue Fassung wartet auf die
 * Freigabe.
 */
final class KnowledgeReviewWorkItemSource implements WorkItemSource {
    public const string KEY = 'knowledge_review';

    /** Vorlauf, ab dem eine Prüfung bzw. das Gültigkeitsende im Vorrat steht. */
    public const int REVIEW_LEAD_DAYS = 14;

    public const int VALIDITY_LEAD_DAYS = 30;

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('knowledge_pages.work_queue.review');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Reminder;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', KnowledgePage::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $today = CarbonImmutable::today();
        $pages = KnowledgePage::query()
            ->where('organization_id', $organization->id)
            ->where('owner_user_id', $viewer->id)
            ->where(static fn (Builder $q) => $q
                ->where('next_review_on', '<', DateRange::dayAfter($today->addDays(self::REVIEW_LEAD_DAYS)))
                ->orWhere('valid_until', '<', DateRange::dayAfter($today->addDays(self::VALIDITY_LEAD_DAYS)))
                ->orWhereIn('status', [KnowledgePageStatus::Draft->value, KnowledgePageStatus::Outdated->value]))
            ->orderBy('title')
            ->get();

        foreach ($pages as $page) {
            $url = route('knowledge.pages.show', $page);
            if ($page->status === KnowledgePageStatus::Draft && $page->hasPendingVersion()) {
                yield new WorkItem($this->key(), $page->getMorphClass() . ':' . $page->id . ':approve:' . $page->version,
                    (string) __('knowledge_pages.work_queue.approve_item', ['title' => $page->title]), $url,
                    (string) __('knowledge_pages.version.label', ['version' => $page->version]), null, true);
            }
            if ($page->status === KnowledgePageStatus::Outdated) {
                yield new WorkItem($this->key(), $page->getMorphClass() . ':' . $page->id . ':outdated', (string) __('knowledge_pages.work_queue.outdated_item', ['title' => $page->title]), $url,
                    $page->valid_until !== null ? (string) __('knowledge_pages.field.valid_until') . ' ' . CarbonFmt::fdate($page->valid_until) : null, null, true);

                continue;
            }
            if ($page->next_review_on !== null && $page->next_review_on->lt($today->addDays(self::REVIEW_LEAD_DAYS + 1)) && $page->isPublished()) {
                yield new WorkItem($this->key(), $page->getMorphClass() . ':' . $page->id . ':review', (string) __('knowledge_pages.work_queue.review_item', ['title' => $page->title]), $url,
                    null, CarbonImmutable::instance($page->next_review_on), true);
            }
            if ($page->valid_until !== null && $page->valid_until->lt($today->addDays(self::VALIDITY_LEAD_DAYS + 1))) {
                yield new WorkItem($this->key(), $page->getMorphClass() . ':' . $page->id . ':valid', (string) __('knowledge_pages.work_queue.validity_item', ['title' => $page->title]), $url,
                    null, CarbonImmutable::instance($page->valid_until), true);
            }
        }
    }
}
