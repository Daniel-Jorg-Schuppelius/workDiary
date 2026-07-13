<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavSyncPage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

/**
 * Ergebnis eines Delta-Syncs (Bauturbo A9): geänderte/neue Karten, gelöschte
 * hrefs und das fortzuschreibende Sync-Token (RFC 6578). Beim ETag-Fallback
 * meldet die Client-Lib nur Karten, deren ETag vom lokalen Stand abweicht.
 */
final class CardDavSyncPage {
    /**
     * @param  list<CardDavCardChange>  $changed
     * @param  list<string>  $deleted
     */
    public function __construct(
        public readonly array $changed,
        public readonly array $deleted,
        public readonly string $syncToken,
    ) {}
}
