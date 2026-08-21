<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavSyncPage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

/**
 * Ergebnis eines CalDAV-Delta-Laufs (Feature 121, MVP-610b): geänderte
 * Objekte, gelöschte hrefs und das fortzuschreibende Sync-Token. Kann der
 * Server kein `sync-collection`, bleibt das Token leer — dann trägt der
 * ETag-Vergleich den Abgleich (CardDAV-Muster).
 */
final class CalDavSyncPage {
    /**
     * @param  list<CalDavEventChange>  $changed
     * @param  list<string>  $deleted  hrefs
     */
    public function __construct(
        public readonly array $changed,
        public readonly array $deleted,
        public readonly string $syncToken,
    ) {}
}
