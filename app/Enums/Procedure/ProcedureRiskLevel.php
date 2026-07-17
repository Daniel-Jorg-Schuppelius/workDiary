<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRiskLevel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProcedureRiskLevel: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.procedure.risk-level.' . $this->value);
    }
}
