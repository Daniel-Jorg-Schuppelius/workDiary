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

enum ProcedureDeviationSeverity: string {
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function isCritical(): bool {
        return $this === self::Critical;
    }
}
