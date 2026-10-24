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
 * Vollständiges Lesen einer Cursor-Paginierung für die Aufgabenimporte: der
 * {@see CursorPaginator} hört am Seitenlimit still auf — hier wird daraus ein
 * Abbruch, damit ein abgeschnittenes Ergebnis nie als vollständig gilt.
 * Wiederholte Cursor bricht schon der Paginator ab.
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
}
