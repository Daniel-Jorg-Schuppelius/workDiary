<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncompleteRemoteReadException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use RuntimeException;

/** Lesen der Quelle abgebrochen (Seitenlimit, ungültige Antwort): der Lauf ist unvollständig, nicht leer. */
final class IncompleteRemoteReadException extends RuntimeException {
    public function __construct(public readonly string $reason) {
        parent::__construct('Quelle nicht vollständig gelesen: ' . $reason);
    }
}
