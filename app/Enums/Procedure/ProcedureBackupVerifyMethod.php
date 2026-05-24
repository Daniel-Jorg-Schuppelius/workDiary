<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupVerifyMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

enum ProcedureBackupVerifyMethod: string {
    case Checksum = 'checksum';
    case RestoreCheck = 'restoreCheck';
    case ManagerConfirmation = 'managerConfirmation';

    public function isAutomatic(): bool {
        return $this === self::Checksum;
    }
}
