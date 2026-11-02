<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemotePages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use APIToolkit\API\Pagination\{CursorPage, CursorPaginator};
use Generator;

/**
 * Vollständiges Lesen einer paginierten Quelle („Lesen ohne stille Lücken“):
 * ein erreichtes Seitenlimit ist ein Abbruch, nie ein leeres Ende, und eine
 * beim Blättern schrumpfende Gesamtzahl heißt, dass etwas über die
 * Seitengrenze gerückt ist. {@see all()} für Cursor (der
 * {@see CursorPaginator} hört am Limit still auf und bricht wiederholte Cursor
 * selbst ab), {@see offset()} für Seitennummern und Offsets.
 */
final class RemotePages {
    /**
     * @param  callable(?string): CursorPage  $fetchPage
     * @return Generator<int, mixed>
     */
    public static function all(callable $fetchPage, int $maxPages): Generator {
        $last = null;
        $paginator = new CursorPaginator(function (?string $cursor) use ($fetchPage, &$last): CursorPage {
            return $last = $fetchPage($cursor);
        }, max(1, $maxPages));

        foreach ($paginator->pages() as $page) {
            yield from $page->getItems();
        }

        if ($last instanceof CursorPage && $last->getNextCursor() !== null) {
            throw new IncompleteRemoteReadException('page_limit');
        }
    }

    /**
     * Liest Seite 1, 2, … bis die Quelle keine weitere meldet. Nennt sie eine
     * Gesamtzahl, darf die nicht sinken; wächst sie, bleibt die Prüfung der
     * Vollständigkeit beim Aufrufer (etwa „gelesen = Gesamtzahl“).
     * Die Einträge kommen fortlaufend nummeriert, damit `iterator_to_array`
     * nichts überschreibt.
     *
     * @template T
     *
     * @param  callable(int): OffsetPage<T>  $fetchPage
     * @return Generator<int, T>
     */
    public static function offset(callable $fetchPage, int $maxPages): Generator {
        $lastTotal = null;
        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $current = $fetchPage($page);
            if ($current->total !== null) {
                if ($lastTotal !== null && $current->total < $lastTotal) {
                    throw new IncompleteRemoteReadException('listing_changed');
                }
                $lastTotal = $current->total;
            }
            foreach ($current->items as $item) {
                yield $item;
            }
            if (! $current->hasMore) {
                return;
            }
        }

        throw new IncompleteRemoteReadException('page_limit');
    }
}
