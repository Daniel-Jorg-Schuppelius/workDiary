<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Intake;

/**
 * Bestätigte Kontoidentität einer Cloud-Dokumenteingang-Verbindung
 * (Feature 080, MVP-351): externe Konto-/Tenant-ID + Anzeigename, wie sie
 * dem Admin nach dem OAuth-Callback zur Bestätigung angezeigt werden.
 */
final readonly class IntakeAccount {
    public function __construct(
        public string $externalId,
        public string $label,
    ) {}
}
