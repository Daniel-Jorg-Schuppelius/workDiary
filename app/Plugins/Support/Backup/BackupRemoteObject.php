<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRemoteObject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Backup;

/**
 * Remote-Objekt im WorkDiary-eigenen Backupbereich (Phase 32, MVP-361):
 * Referenz + Name + Größe, wie es Listen-/Lösch-/Download-Operationen
 * benötigen. Adapter listen NIE außerhalb des eigenen Bereichs.
 */
final readonly class BackupRemoteObject {
    public function __construct(
        /** Providerstabile Referenz (Item-ID bzw. Pfad). */
        public string $ref,
        /** Objektname relativ zum Backupbereich (Pseudonym-Pfad). */
        public string $name,
        public int $size,
        public ?string $modifiedAt = null,
    ) {}
}
