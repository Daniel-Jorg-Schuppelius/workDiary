<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupStorageTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Contracts\HasLabel;

enum ProcedureBackupStorageTarget: string implements HasLabel {
    case Attachment = 'attachment';
    case External = 'external';

    public function label(): string {
        return (string) __('enums.procedure.backup-storage-target.' . $this->value);
    }
}
