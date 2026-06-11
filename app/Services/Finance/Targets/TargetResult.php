<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TargetResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance\Targets;

use App\Models\ExternalReference;

/**
 * Ergebnis einer Ziel-Übergabe (Feature 045, Teil B): API-Ziele liefern eine
 * {@see ExternalReference} (z. B. Lexoffice-Rechnungsentwurf), Datei-Ziele
 * einen Storage-Pfad. `externalUrl` nur, wenn das Ziel eine aufrufbare
 * App-URL hergibt — sonst null.
 */
final class TargetResult {
    public function __construct(
        public readonly ?ExternalReference $externalReference = null,
        public readonly ?string $filePath = null,
        public readonly ?string $externalUrl = null,
    ) {}
}
