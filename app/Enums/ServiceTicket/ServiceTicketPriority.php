<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketPriority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

enum ServiceTicketPriority: string {
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string {
        return match ($this) {
            self::Low => __('Niedrig'),
            self::Normal => __('Normal'),
            self::High => __('Hoch'),
            self::Urgent => __('Dringend'),
        };
    }
}
