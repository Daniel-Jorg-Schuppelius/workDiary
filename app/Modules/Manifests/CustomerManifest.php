<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Kunden und Kundenportal“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CustomerManifest extends Manifest {
    public function code(): string {
        return 'customer';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Kunden und Kundenportal';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Customer',
            'Customers',
            'CustomerPortal',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'customer_circular_recipients',
            'customer_circulars',
            // Kundenfristen aus Regelsätzen (Feature 197, MVP-1630/1632).
            'customer_deadline_events',
            'customer_deadline_profiles',
            'customer_deadlines',
            'customer_geofences',
            'customer_intake_events',
            'customer_intake_messages',
            'customer_intake_upload_links',
            'customer_intakes',
            'customer_merge_dismissals',
            'customer_queries',
            'customer_services',
            'customers',
            'foreign_customers',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Customers,
            PermissionGroup::CustomerPortal,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            // Kundenfristen (Feature 197): Regelsätze Steuerberatung und ambulante Pflege.
            \App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet::class => [
                \App\Services\Customer\Deadlines\RuleSets\TaxAdvisoryDeadlineRuleSet::class,
                \App\Services\Customer\Deadlines\RuleSets\HomeCareDeadlineRuleSet::class,
            ],
            \App\Services\WorkQueue\Contracts\WorkItemSource::class => [
                \App\Services\Customer\WorkQueue\CustomerIntakeWorkItemSource::class,
                \App\Services\Customer\WorkQueue\CustomerDeadlineWorkItemSource::class,
            ],
            // Kundenfristen (Feature 197, MVP-1633): zuständige Person = delegierbare Arbeit, Übergabe beim Austritt.
            \App\Services\Delegation\Contracts\DelegableWork::class => [
                \App\Services\Customer\WorkQueue\CustomerDeadlineWorkItemSource::class,
            ],
            \App\Services\Org\Contracts\OffboardingHandover::class => [
                \App\Services\Customer\Offboarding\CustomerDeadlineOffboardingHandover::class,
            ],
            \App\Services\Cockpit\Contracts\CockpitPanel::class => [
                \App\Services\Customer\Cockpit\CustomerDeadlineCockpitPanel::class,
            ],
            \App\Services\Calendar\Contracts\CalendarEventSource::class => [
                \App\Services\Customer\Calendar\CustomerDeadlineCalendarSource::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Customer\DeadlineScans\CustomerDeadlineScan::class,
            ],
            \App\Services\Reporting\Contracts\EarlyWarningSource::class => [
                \App\Services\Customer\EarlyWarnings\OverdueCustomerDeadlineWarningSource::class,
            ],
            \App\Services\Classification\Contracts\ProfileInstallStep::class => [
                \App\Services\Customer\Install\DeadlineRuleSetInstallStep::class,
            ],
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Customer\Demo\CustomerDeadlineDemoBlock::class,
            ],
            \App\Services\Search\Contracts\SearchDomain::class => [
                \App\Services\Customer\Search\CustomerSearchDomain::class,
            ],
            \App\Services\Mcp\Contracts\McpTool::class => [
                \App\Services\Customer\Mcp\CustomersTool::class,
                \App\Services\Customer\Mcp\CreateCustomerTool::class,
            ],
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Customer\Import\ContactPersonSpec::class,
                \App\Services\Customer\Import\CustomerSpec::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Customer\Retention\CustomerRetentionPolicies::class,
            ],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            // Feature 197 (MVP-1632): ein abgeschlossener Auftrag mit Periode erledigt die zugeordneten Kundenfristen.
            \App\Events\Diary\OrderCompleted::class => [
                \App\Listeners\Customer\CompleteCustomerDeadlinesOnOrderCompleted::class,
            ],
        ];
    }
}
