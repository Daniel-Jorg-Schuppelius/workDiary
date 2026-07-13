<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavCardChange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use Sabre\VObject\Component\VCard;

/**
 * Eine vom Server als neu/geändert gemeldete Karte (Bauturbo A9).
 * `$vcard` ist null, wenn der Abruf/das Parsen serverseitig scheiterte —
 * der Importer überspringt solche Karten und versucht sie beim nächsten
 * Lauf erneut (der ETag wird dann nicht fortgeschrieben).
 */
final class CardDavCardChange {
    public function __construct(
        public readonly string $href,
        public readonly string $etag,
        public readonly ?VCard $vcard,
    ) {}
}
