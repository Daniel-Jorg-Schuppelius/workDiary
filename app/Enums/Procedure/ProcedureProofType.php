<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureProofType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

enum ProcedureProofType: string {
    case Backup = 'backup';
    case File = 'file';
    case Photo = 'photo';
    case Measure = 'measure';
    case Signature = 'signature';

    public function label(): string {
        return (string) __('enums.procedure.proof-type.' . $this->value);
    }
}
