<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Numbering;

enum NumberScope: string {
    case ServiceTicket = 'service_ticket';
    case Asset = 'asset';
    case Customer = 'customer';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string {
        return match ($this) {
            self::ServiceTicket => __('Service-Ticket'),
            self::Asset => __('Asset'),
            self::Customer => __('Kunde'),
            self::Invoice => __('Rechnung'),
            self::CreditNote => __('Gutschrift'),
        };
    }
}
