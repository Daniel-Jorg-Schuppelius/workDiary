<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemotePagesOffsetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Plugins\Support;

use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, OffsetPage, RemotePages};
use PHPUnit\Framework\TestCase;

/** Seitenweises Lesen ohne stille Lücken (Konsolidierung kon-07): Seitenlimit und schrumpfende Gesamtzahl sind Abbrüche. */
class RemotePagesOffsetTest extends TestCase {
    public function test_reads_all_pages_in_order_with_sequential_keys(): void {
        $requested = [];
        $pages = [1 => ['a', 'b'], 2 => ['c'], 3 => ['d']];

        $items = iterator_to_array(RemotePages::offset(function (int $page) use ($pages, &$requested): OffsetPage {
            $requested[] = $page;

            return new OffsetPage($pages[$page], hasMore: $page < 3, total: 4);
        }, 10));

        $this->assertSame(['a', 'b', 'c', 'd'], $items, 'Fortlaufende Schlüssel — iterator_to_array überschreibt nichts.');
        $this->assertSame([1, 2, 3], $requested);
    }

    public function test_page_limit_is_an_incomplete_read_after_yielding_what_was_read(): void {
        $seen = [];
        try {
            foreach (RemotePages::offset(fn (int $page): OffsetPage => new OffsetPage([$page], hasMore: true), 2) as $item) {
                $seen[] = $item;
            }
            $this->fail('Seitenlimit muss werfen.');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }
        $this->assertSame([1, 2], $seen, 'Gelesene Seiten kommen vor dem Abbruch beim Aufrufer an.');
    }

    public function test_shrinking_total_is_a_changed_listing(): void {
        $totals = [1 => 10, 2 => 9];
        $this->expectException(IncompleteRemoteReadException::class);
        $this->expectExceptionMessage('listing_changed');

        iterator_to_array(RemotePages::offset(fn (int $page): OffsetPage => new OffsetPage(['x'], hasMore: true, total: $totals[$page]), 5));
    }

    public function test_growing_total_and_missing_total_are_tolerated(): void {
        $totals = [1 => 2, 2 => null, 3 => 3];

        $items = iterator_to_array(RemotePages::offset(fn (int $page): OffsetPage => new OffsetPage([$page], hasMore: $page < 3, total: $totals[$page]), 5));

        $this->assertSame([1, 2, 3], $items);
    }

    public function test_an_empty_last_page_ends_the_read(): void {
        $items = iterator_to_array(RemotePages::offset(fn (int $page): OffsetPage => $page === 1 ? new OffsetPage(['only'], hasMore: true) : new OffsetPage([], hasMore: false), 5));

        $this->assertSame(['only'], $items);
    }

    public function test_the_limit_counts_at_least_one_page(): void {
        $items = iterator_to_array(RemotePages::offset(fn (int $page): OffsetPage => new OffsetPage(['one'], hasMore: false), 0));

        $this->assertSame(['one'], $items);
    }
}
