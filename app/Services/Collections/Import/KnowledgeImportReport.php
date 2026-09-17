<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeImportReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use App\Models\ContentCollection;

/** Ergebnis einer Übernahme aus Obsidian oder OneNote (MVP-815). */
final class KnowledgeImportReport {
    public int $created = 0;

    /** Schon früher übernommen — bleibt unverändert (Einbahn, kein Abgleich). */
    public int $skipped = 0;

    public int $truncated = 0;

    public int $collections = 0;

    public int $linksResolved = 0;

    public int $linksUnresolved = 0;

    /** Obergrenze je Lauf erreicht; ein weiterer Lauf übernimmt den Rest. */
    public bool $limited = false;

    public ?ContentCollection $root = null;

    /** @return array{created: int, skipped: int, truncated: int, collections: int, links: int, unresolved: int} */
    public function counts(): array {
        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'truncated' => $this->truncated,
            'collections' => $this->collections,
            'links' => $this->linksResolved,
            'unresolved' => $this->linksUnresolved,
        ];
    }
}
