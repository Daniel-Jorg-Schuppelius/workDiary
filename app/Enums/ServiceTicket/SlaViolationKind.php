<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer SLA-Verletzung: Reaktionszeit (erste Reaktion zu spät) oder
 * Lösungszeit (Lösung zu spät). Spiegelt die beiden SLA-Fristen am Ticket
 * (reaction_due_at / resolution_due_at).
 */
enum SlaViolationKind: string implements HasLabel {
    use HasOptions;

    case ResponseTime = 'responseTime';
    case ResolutionTime = 'resolutionTime';

    public function label(): string {
        return match ($this) {
            self::ResponseTime => __('enums.sla.violationKind.responseTime'),
            self::ResolutionTime => __('enums.sla.violationKind.resolutionTime'),
        };
    }
}
