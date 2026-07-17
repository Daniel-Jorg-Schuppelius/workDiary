<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketCloseCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Abschlusscode (Feature 065, MVP-151): WIE wurde das Ticket beendet. */
enum TicketCloseCode: string implements HasLabel {
    use HasOptions;

    case Solved = 'solved';
    case Workaround = 'workaround';
    case Duplicate = 'duplicate';
    case NoFault = 'no_fault';
    case Rejected = 'rejected';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Solved => (string) __('Gelöst'),
            self::Workaround => (string) __('Umgehungslösung'),
            self::Duplicate => (string) __('Duplikat'),
            self::NoFault => (string) __('Kein Fehler'),
            self::Rejected => (string) __('Abgelehnt'),
            self::Other => (string) __('Sonstiges'),
        };
    }
}
