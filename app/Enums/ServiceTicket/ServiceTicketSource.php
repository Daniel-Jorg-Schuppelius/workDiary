<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ServiceTicketSource: string implements HasLabel {
    use HasOptions;

    case Manual = 'manual';
    case MaintenancePlan = 'maintenance_plan';
    case OpenIssue = 'open_issue';
    case Email = 'email';
    case CustomerPortal = 'customer_portal';
    case Api = 'api';

    public function label(): string {
        return match ($this) {
            self::Manual => __('enums.service_ticket.service_ticket_source.manual'),
            self::MaintenancePlan => __('enums.service_ticket.service_ticket_source.maintenance_plan'),
            self::OpenIssue => __('enums.service_ticket.service_ticket_source.open_issue'),
            self::Email => __('enums.service_ticket.service_ticket_source.email'),
            self::CustomerPortal => __('enums.service_ticket.service_ticket_source.customer_portal'),
            self::Api => __('enums.service_ticket.service_ticket_source.api'),
        };
    }
}
