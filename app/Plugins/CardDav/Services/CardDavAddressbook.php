<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavAddressbook.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

/**
 * Discovery-Ergebnis (Bauturbo A9): ein auf dem Server gefundenes Adressbuch,
 * das der Admin als Sync-Quelle wählen kann.
 */
final class CardDavAddressbook {
    public function __construct(
        public readonly string $url,
        public readonly string $name,
    ) {}
}
