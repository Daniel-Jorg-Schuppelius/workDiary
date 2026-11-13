<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageReviewScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\DeadlineScans;

use App\Enums\Knowledge\KnowledgePageStatus;
use App\Enums\Notification\NotificationEvent;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Knowledge\Pages\KnowledgePageService;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Prüfintervall und Gültigkeit der Wissensseiten (Feature 191, MVP-1665):
 *
 * 1. Seiten, deren Gültigkeitsdatum abgelaufen ist, werden veraltet.
 * 2. Je verantwortlicher Person EINE gebündelte Erinnerung für Seiten, deren
 *    Prüfung in {@see REVIEW_WARN_DAYS} Tagen fällig ist oder deren Gültigkeit
 *    in {@see VALIDITY_WARN_DAYS} Tagen endet. Jede Seite meldet je Frist
 *    einmal (`review_warned_at`, `valid_warned_at`); „Stimmt, aktuell“ und
 *    eine neue Gültigkeit setzen neu an.
 */
final class KnowledgePageReviewScan extends AbstractDeadlineScan {
    public const int REVIEW_WARN_DAYS = 7;

    public const int VALIDITY_WARN_DAYS = 30;

    public function __construct(private readonly KnowledgePageService $pages) {}

    public function key(): string {
        return 'knowledge_page_review';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $this->expire();

        $today = CarbonImmutable::today();
        $organizationIds = $this->due($today)->distinct()->pluck('organization_id');
        $sent = 0;
        foreach (Organization::query()->whereIn('id', $organizationIds)->orderBy('id')->get() as $organization) {
            if (! $this->moduleActiveFor($organization)) {
                continue;
            }
            $sent += (int) OrganizationContext::run($organization, fn (): int => $this->scanOrganization($dispatcher, $organization, $today));
        }

        return $sent;
    }

    private function expire(): void {
        KnowledgePage::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('status', KnowledgePageStatus::Valid->value)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', DateRange::day(CarbonImmutable::today()->toDateString()))
            ->orderBy('id')
            ->each(function (KnowledgePage $page): void {
                if ($this->moduleActiveFor((int) $page->organization_id)) {
                    $this->pages->expire($page);
                }
            });
    }

    private function scanOrganization(NotificationDispatcher $dispatcher, Organization $organization, CarbonImmutable $today): int {
        $pages = $this->due($today)
            ->where('organization_id', $organization->id)
            ->with('owner')
            ->orderBy('title')
            ->get();
        $sent = 0;
        foreach ($pages->groupBy('owner_user_id') as $bundle) {
            $sent += $this->notifyBundle($dispatcher, $bundle, $today);
        }

        return $sent;
    }

    /** @param  Collection<int, KnowledgePage>  $bundle */
    private function notifyBundle(NotificationDispatcher $dispatcher, Collection $bundle, CarbonImmutable $today): int {
        $first = $bundle->first();
        if (! $first instanceof KnowledgePage) {
            return 0;
        }
        $recipient = $first->owner;
        $titles = $bundle->pluck('title')->unique()->take(5)->implode(', ');
        $titleParams = ['count' => $bundle->count()];
        $messageParams = ['titles' => $titles];
        $dueAt = $bundle->map(static fn (KnowledgePage $page) => $page->next_review_on ?? $page->valid_until)->filter()->sort()->first();
        $sent = $dispatcher->notify(NotificationEvent::KnowledgePageReviewDue, $first, $recipient instanceof User ? $recipient : null, [
            'title' => (string) __('knowledge_pages.notification.review.title', $titleParams),
            'title_key' => 'knowledge_pages.notification.review.title',
            'title_params' => $titleParams,
            'message' => (string) __('knowledge_pages.notification.review.message', $messageParams),
            'message_key' => 'knowledge_pages.notification.review.message',
            'message_params' => $messageParams,
            'url' => route('knowledge.pages.index', ['filter' => 'review']),
            'due_at' => $dueAt,
        ], dedup: true);

        // Gemeldet ist gemeldet — auch ohne Empfänger, sonst käme dieselbe Seite täglich wieder.
        $reviewHorizon = $today->addDays(self::REVIEW_WARN_DAYS + 1);
        $validityHorizon = $today->addDays(self::VALIDITY_WARN_DAYS + 1);
        foreach ($bundle as $page) {
            $changes = [];
            if ($page->review_warned_at === null && $page->next_review_on !== null && $page->next_review_on->lt($reviewHorizon)) {
                $changes['review_warned_at'] = now();
            }
            if ($page->valid_warned_at === null && $page->valid_until !== null && $page->valid_until->lt($validityHorizon)) {
                $changes['valid_warned_at'] = now();
            }
            if ($changes !== []) {
                KnowledgePage::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($page->id)->update($changes);
            }
        }

        return $sent;
    }

    /** @return Builder<KnowledgePage> */
    private function due(CarbonImmutable $today): Builder {
        return KnowledgePage::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('owner_user_id')
            ->whereNotNull('approved_version')
            ->where(static fn (Builder $q) => $q
                ->where(static fn (Builder $r) => $r->whereNull('review_warned_at')->whereNotNull('next_review_on')
                    ->where('next_review_on', '<', DateRange::dayAfter($today->addDays(self::REVIEW_WARN_DAYS))))
                ->orWhere(static fn (Builder $v) => $v->whereNull('valid_warned_at')->whereNotNull('valid_until')
                    ->where('valid_until', '<', DateRange::dayAfter($today->addDays(self::VALIDITY_WARN_DAYS)))));
    }
}
