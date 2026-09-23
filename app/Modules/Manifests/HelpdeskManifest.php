<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Helpdesk“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class HelpdeskManifest extends Manifest {
    public function code(): string {
        return 'helpdesk';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Helpdesk';
    }

    public function licenseCode(): string {
        return 'module.helpdesk';
    }

    public function description(): string {
        return 'Tickets mit Queues, Konversation, SLA und Omnichannel-Eingang.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'ServiceTicket',
            'Helpdesk',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'change_asset',
            'change_templates',
            'change_ticket',
            'changes',
            'problem_reports',
            'problem_ticket',
            'problems',
            'service_ticket_links',
            'service_ticket_messages',
            'service_ticket_watchers',
            'service_tickets',
            'sla_clock_segments',
            'sla_contract_quotas',
            'sla_contracts',
            'sla_violations',
            'ticket_routing_rules',
            'ticket_rule_executions',
            'ticket_satisfaction',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'service-tickets.*',
            'helpdesk.*',
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'zammad',
        ];
    }
}
