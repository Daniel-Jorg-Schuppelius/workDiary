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

enum ServiceTicketSource: string {
    case Manual = 'manual';
    case MaintenancePlan = 'maintenance_plan';
    case OpenIssue = 'open_issue';
    case Email = 'email';
    case CustomerPortal = 'customer_portal';
    case Api = 'api';

    public function label(): string {
        return match ($this) {
            self::Manual => __('Manuell'),
            self::MaintenancePlan => __('Wartungsplan'),
            self::OpenIssue => __('Offene Punkte'),
            self::Email => __('E-Mail'),
            self::CustomerPortal => __('Kundenportal'),
            self::Api => __('API'),
        };
    }
}
