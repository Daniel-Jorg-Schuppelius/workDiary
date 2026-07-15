<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Backup;

/**
 * Bestätigte Kontoidentität einer Backupziel-Verbindung (Phase 32, MVP-361).
 */
final readonly class BackupAccount {
    public function __construct(
        public string $externalId,
        public string $label,
    ) {}
}
