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

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\ServiceTicket\Demo\HelpdeskDemoBlock::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\ServiceTicket\DeadlineScans\HelpdeskFollowupScans::class,
                \App\Services\ServiceTicket\DeadlineScans\SlaQuotaScan::class,
                \App\Services\ServiceTicket\DeadlineScans\SlaTicketScan::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\ServiceTicket\Search\ServiceTicketSource::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\ServiceTicket\Retention\HelpdeskRetentionPolicies::class,
            ],
            \App\Services\Mail\Contracts\MailIntakeHandler::class => [
                \App\Services\ServiceTicket\Mail\TicketThreadMailIntakeHandler::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array {
        return [
            \App\Services\Learning\Contracts\QuestionTicketOpener::class => \App\Services\ServiceTicket\Learning\LearningQuestionTicketOpener::class,
            \App\Services\Asset\Contracts\ServiceLevelResolver::class => \App\Services\ServiceTicket\SlaTimer::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Asset\MaintenanceDue::class => [
                \App\Listeners\Helpdesk\OpenMaintenanceTicket::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Services\ServiceTicket\Contracts\KnownErrorPublisher::class => \App\Services\ServiceTicket\Contracts\NullKnownErrorPublisher::class,
        ];
    }
}
