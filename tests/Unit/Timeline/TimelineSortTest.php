<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimelineSortTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Timeline;

use App\Services\Timeline\{DiaryEntryTimelineService, TimelineItem};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Reiner Unit-Test (ohne DB) für den Merge-/Sortier-Kern der Timeline.
 */
class TimelineSortTest extends TestCase {
    private function item(string $id, ?string $at): TimelineItem {
        return new TimelineItem(
            id: $id,
            type: 'comment',
            icon: 'chat_bubble',
            occurredAt: $at !== null ? CarbonImmutable::parse($at) : null,
            actor: null,
            title: 'Test',
        );
    }

    public function test_items_are_sorted_descending_by_occurred_at(): void {
        $items = [
            $this->item('a', '2030-01-01 10:00:00'),
            $this->item('b', '2030-01-03 10:00:00'),
            $this->item('c', '2030-01-02 10:00:00'),
        ];

        $result = DiaryEntryTimelineService::sortAndSlice($items, 10);

        $this->assertSame(['b', 'c', 'a'], array_map(fn(TimelineItem $i) => $i->id, $result['items']));
        $this->assertFalse($result['hasMore']);
    }

    public function test_null_timestamps_sort_last_and_ties_are_stable_by_id(): void {
        $items = [
            $this->item('z-null', null),
            $this->item('b-tie', '2030-01-01 10:00:00'),
            $this->item('a-tie', '2030-01-01 10:00:00'),
        ];

        $result = DiaryEntryTimelineService::sortAndSlice($items, 10);

        $this->assertSame(['a-tie', 'b-tie', 'z-null'], array_map(fn(TimelineItem $i) => $i->id, $result['items']));
    }

    public function test_limit_and_offset_paginate_and_flag_has_more(): void {
        $items = [
            $this->item('a', '2030-01-04 10:00:00'),
            $this->item('b', '2030-01-03 10:00:00'),
            $this->item('c', '2030-01-02 10:00:00'),
            $this->item('d', '2030-01-01 10:00:00'),
        ];

        $page1 = DiaryEntryTimelineService::sortAndSlice($items, 2);
        $this->assertSame(['a', 'b'], array_map(fn(TimelineItem $i) => $i->id, $page1['items']));
        $this->assertTrue($page1['hasMore']);

        $page2 = DiaryEntryTimelineService::sortAndSlice($items, 2, 2);
        $this->assertSame(['c', 'd'], array_map(fn(TimelineItem $i) => $i->id, $page2['items']));
        $this->assertFalse($page2['hasMore']);
    }

    public function test_to_array_exposes_dto_shape(): void {
        $item = $this->item('comment:1', '2030-01-01 10:00:00');
        $data = $item->toArray();

        $this->assertSame('comment:1', $data['id']);
        $this->assertSame('comment', $data['type']);
        $this->assertSame('internal', $data['visibility']);
        $this->assertArrayHasKey('occurred_at', $data);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('url', $data);
    }
}
