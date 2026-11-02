<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffsetPage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

/**
 * Eine Seite einer seitenweise (Seitennummer oder Offset) gelesenen Liste
 * für {@see RemotePages::offset()}: die Einträge, ob die Quelle weitere
 * Seiten meldet, und ihre Gesamtzahl, sofern sie eine nennt.
 *
 * @template-covariant T
 */
final class OffsetPage {
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly bool $hasMore,
        public readonly ?int $total = null,
    ) {}
}
