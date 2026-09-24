<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanDriver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing\Scanning;

use App\Enums\Whistleblowing\AttachmentScanStatus;

/**
 * Pluggbarer Malware-Scanner fuer Meldeanhaenge. Implementierungen MUESSEN den
 * Inhalt in einer gesandboxten Umgebung verarbeiten (Abschnitt 25).
 */
interface ScanDriver {
    /**
     * Liefert das Pruefurteil oder null, wenn kein Urteil moeglich ist
     * (fail-safe → der Anhang bleibt in Quarantaene).
     */
    public function scan(string $absolutePath, ?string $mime): ?AttachmentScanStatus;
}
