<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarSeriesGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\Organization;
use App\Services\Integration\{InboxActionService, InboxGroupBooker};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Serien aus dem Kalender-Rückimport als Inbox-Gruppe (MVP-977): alle offenen
 * Vorkommen einer Serie auf einmal als Termine anlegen oder verwerfen. Je
 * Kalender-Plugin eine Unterklasse mit seiner Kennung.
 */
abstract class CalendarSeriesGroupBooker implements InboxGroupBooker {
    private const PREVIEW = 10;

    public function __construct(private readonly InboxActionService $actions) {}

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = $this->openItems($organization)
            ->groupBy(static fn (IntegrationInboxItem $item): string => (string) ($item->remote_snapshot['series_uid'] ?? ''))
            ->filter(static fn (EloquentCollection $items, string $uid): bool => $uid !== '')
            ->map(function (EloquentCollection $items, string $uid): array {
                $sorted = $items->sortBy(static fn (IntegrationInboxItem $item): string => (string) ($item->mapped_snapshot['started_at'] ?? ''))->values();
                $entries = $sorted->take(self::PREVIEW)->map(static function (IntegrationInboxItem $item): array {
                    $start = $item->mapped_snapshot['started_at'] ?? null;
                    $end = $item->mapped_snapshot['ended_at'] ?? null;
                    $minutes = is_string($start) && is_string($end) ? (int) CarbonImmutable::parse($start)->diffInMinutes(CarbonImmutable::parse($end)) : 0;

                    return ['started_at' => $start, 'ended_at' => $end, 'minutes' => $minutes, 'description' => $item->display_title, 'user_email' => null];
                })->all();

                return [
                    'plugin_id' => $this->pluginId(),
                    'form' => 'calendar_series',
                    'group_key' => $uid,
                    'title' => (string) ($sorted->first()?->remote_snapshot['series_title'] ?? $sorted->first()->display_title ?? ''),
                    'count' => $sorted->count(),
                    'minutes' => array_sum(array_column($entries, 'minutes')),
                    'entries' => $entries,
                    'entries_more' => max(0, $sorted->count() - self::PREVIEW),
                ];
            })
            ->values();

        return $groups;
    }

    public function rules(): array {
        return [];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        $created = 0;
        foreach ($this->seriesItems($organization, $groupKey) as $item) {
            $this->actions->createFromItem($item);
            $created++;
        }

        return ['created' => $created, 'skipped' => 0];
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        $count = 0;
        foreach ($this->seriesItems($organization, $groupKey) as $item) {
            $this->actions->dismiss($item);
            $count++;
        }

        return $count;
    }

    /** @return EloquentCollection<int, IntegrationInboxItem> */
    private function seriesItems(Organization $organization, string $uid): EloquentCollection {
        return $this->openItems($organization)
            ->filter(static fn (IntegrationInboxItem $item): bool => ($item->remote_snapshot['series_uid'] ?? null) === $uid)
            ->values();
    }

    /** @return EloquentCollection<int, IntegrationInboxItem> */
    private function openItems(Organization $organization): EloquentCollection {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereLikeEscaped('dedupe_key', 'calendar-proposal:', 'prefix')
            ->get();
    }
}
