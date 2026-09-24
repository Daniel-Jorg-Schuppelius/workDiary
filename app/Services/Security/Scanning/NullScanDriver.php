<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullScanDriver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing\Scanning;

use App\Enums\Whistleblowing\AttachmentScanStatus;

/**
 * Default-Treiber: kein Scanner konfiguriert → kein Urteil. Anhaenge bleiben
 * fail-safe in Quarantaene und werden nie ausgeliefert.
 */
class NullScanDriver implements ScanDriver {
    public function scan(string $absolutePath, ?string $mime): ?AttachmentScanStatus {
        return null;
    }
}
