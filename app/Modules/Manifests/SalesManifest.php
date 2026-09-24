<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Vertrieb & Abrechnung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SalesManifest extends Manifest {
    public function code(): string {
        return 'sales';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Vertrieb & Abrechnung';
    }

    public function licenseCode(): string {
        return 'module.vertrieb';
    }

    public function description(): string {
        return 'Kunden, Projekte, Rechnungen und Abrechnung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Sales',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'bookable_services',
            'commission_rules',
            'commission_settlement_runs',
            'leads',
            'quote_items',
            'quotes',
            'sales_discount_group_overrides',
            'sales_discount_groups',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'customers.*',
            'leads.*',
            'commissions.*',
            'commission-rules.*',
            'commission-runs.*',
            'surveys.*',
            'suppliers.*',
            'api.suppliers.*',
            'api.legacy.suppliers.*',
            'projects.*',
            'billing.feed',
            'invoices.*',
            'api.invoices.*',
            'api.legacy.invoices.*',
            'invoice-schedules.*',
            'quotes.*',
            'lexoffice.*',
            'events.*',
            'event-categories.*',
            'permits.*',
            'materials.*',
            'finance.incoming-invoices.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'sales',
            ],
            'items' => [],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Sales\DeadlineScans\QuoteFollowUpScan::class,
            ],
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Sales\Import\QuoteSpec::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Sales\Retention\SalesRetentionPolicies::class,
            ],
        ];
    }
}
