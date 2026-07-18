<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Contracts\HasLabel;

enum ProcedureDeviationSeverity: string implements HasLabel {
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.procedure.deviation-severity.' . $this->value);
    }

    public function isCritical(): bool {
        return $this === self::Critical;
    }
}
