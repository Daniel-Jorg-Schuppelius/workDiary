<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Impact/Urgency-Stufe (Feature 065, MVP-151) — gemeinsamer int-Wrapper
 * für beide Dimensionen (1 = niedrig … 3 = hoch); die Prioritätsableitung
 * (Impact × Urgency) kommt mit den Routing-Regeln in P3.
 */
enum TicketSeverity: int implements HasLabel {
    use HasOptions;

    case Low = 1;
    case Medium = 2;
    case High = 3;

    public function label(): string {
        return match ($this) {
            self::Low => (string) __('Niedrig'),
            self::Medium => (string) __('Mittel'),
            self::High => (string) __('Hoch'),
        };
    }
}
