<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sync;

use RuntimeException;

/**
 * Ein ÄNDERNDER Offline-Befehl trifft auf einen inzwischen veränderten Stand
 * (Feature 035 Phase 3; Audit 2026-08, W4.1). Bewusst KEINE Ablehnung: Eine
 * Ablehnung heißt „so geht das nicht", ein Konflikt heißt „jemand war
 * schneller" — der Nutzer muss den fremden Stand sehen und entscheiden, ob er
 * ihn übernimmt oder seine Fassung erneut sendet. Ein „Erneut anwenden" ohne
 * diese Entscheidung würde die fremde Änderung blind überschreiben.
 *
 * @phpstan-type ServerState array<string, mixed>
 */
class SyncConflictException extends RuntimeException {
    /**
     * @param  array<string, mixed>  $serverState  Der aktuelle Server-Stand für die Anzeige.
     */
    public function __construct(
        string $message,
        public readonly array $serverState,
        public readonly string $currentVersion,
    ) {
        parent::__construct($message);
    }
}
