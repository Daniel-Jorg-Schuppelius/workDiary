<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Contracts;

/**
 * Schmaler, testbarer Zugriff auf einen CalDAV-Server (Feature 058, MVP-126):
 * ein Kalenderobjekt (`{name}.ics`) idempotent anlegen/ersetzen (PUT) bzw.
 * löschen (DELETE). Kapselt HTTP/Auth; die Publish-Logik hängt nur hieran und
 * wird im Test gemockt (kein echter Server).
 */
interface CalDavGateway {
    /** Legt das Kalenderobjekt an bzw. ersetzt es (idempotent). true = erfolgreich. */
    public function putObject(string $objectName, string $ics): bool;

    /** Löscht das Kalenderobjekt; ein bereits fehlendes Objekt (404) gilt als Erfolg. */
    public function deleteObject(string $objectName): bool;

    /** Liveness/Auth-Check: true, wenn die Ziel-Collection mit den Zugangsdaten erreichbar ist. */
    public function ping(): bool;
}
