<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HashChainedJournalEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Journal;

use App\Models\Concerns\{HashChainable, HashChained};

/**
 * Journal mit Hash-Kette (GoBD): Kanonik je Modell bleibt in `hashPayload()`
 * — der Baustein ändert keine Zeile und keinen Hash (MVP-864).
 */
abstract class HashChainedJournalEntry extends JournalEntry implements HashChainable {
    use HashChained;

    protected function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }
}
