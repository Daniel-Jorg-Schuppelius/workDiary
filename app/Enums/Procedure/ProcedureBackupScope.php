<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Contracts\HasLabel;

enum ProcedureBackupScope: string implements HasLabel {
    case Config = 'config';
    case Database = 'database';
    case FullSystem = 'fullSystem';
    case CustomScript = 'customScript';

    public function label(): string {
        return (string) __('enums.procedure.backup-scope.' . $this->value);
    }
}
