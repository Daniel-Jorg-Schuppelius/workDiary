<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Finanzschnittstelle“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class FinanceManifest extends Manifest {
    public function code(): string {
        return 'finance';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Finanzschnittstelle';
    }

    public function licenseCode(): string {
        return 'module.finance';
    }

    public function description(): string {
        return 'Finanz-/DATEV-Schnittstelle.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Finance',
            'Accounting',
            'AccountingMigration',
            'Migration',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'accounting_accounts',
            'accounting_budgets',
            'accounting_entries',
            'accounting_entry_lines',
            'accounting_events',
            'accounting_filing_obligations',
            'accounting_fiscal_years',
            'accounting_migration_events',
            'accounting_migration_items',
            'accounting_migration_runs',
            'accounting_open_item_settlements',
            'accounting_open_items',
            'accounting_periods',
            'accounting_posting_rules',
            'accounting_profiles',
            'accounting_recurring_runs',
            'accounting_recurring_templates',
            'accounting_sovereignty_periods',
            'accounting_tax_codes',
            'accounting_taxation_periods',
            'accounting_transfers',
            'accounting_vat_extensions',
            'accounting_vat_filing_periods',
            'accounting_vouchers',
            'bank_accounts',
            'bank_statements',
            'bank_transactions',
            'billing_transfer_events',
            'billing_transfer_items',
            'billing_transfer_positions',
            'billing_transfers',
            'cost_center_rules',
            'cost_centers',
            'datev_booking_batches',
            'datev_booking_events',
            'datev_booking_sources',
            'fixed_assets',
            'payment_allocations',
            'payment_reconciliation_events',
            'payment_run_items',
            'payment_runs',
            'sepa_mandates',
            'tax_rules',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'admin.orgamax.*',
            'finance.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Finance,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'finance.open-times.index',
                'finance.dunning.index',
                'finance.transfers.index',
                'finance.reconciliation.index',
                'finance.bank-accounts.index',
                'finance.datev.index',
                'finance.gobd.index',
                'finance.procedure-documentation.index',
                'finance.payment-runs.index',
                'finance.mandates.index',
                'finance.accounting.setup',
                'finance.accounting.accounts.index',
                'finance.accounting.journal.index',
                'finance.accounting.inbox.index',
                'finance.accounting.open-items.index',
                'finance.accounting.recurring.index',
                'finance.accounting.closing.index',
                'finance.accounting.filings.index',
                'reports.accounting.index',
                'reports.accounting.recapitulative',
                'reports.accounting.bwa',
                'finance.accounting.rules.index',
                'finance.accounting.fixed-assets.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'lexoffice',
            'sevdesk',
            'easybill',
            'orgamax',
            'buchhaltungsbutler',
        ];
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array {
        return [
            \App\Services\Invoicing\Contracts\PaymentStatusProvider::class => \App\Services\Finance\ReconciliationService::class,
            \App\Services\Passenger\Contracts\CashBookPosting::class => \App\Services\Finance\CashBookService::class,
            \App\Services\Stammdaten\Contracts\ContactPushTarget::class => \App\Services\Finance\Accounting\ContactPushService::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Finance\Demo\FinanceDemoBlock::class,
            ],
            \App\Plugins\Support\Contracts\PluginCapabilitySource::class => [
                \App\Services\Finance\Targets\FacturationCapabilitySource::class,
            ],
            \App\Services\Navigation\Contracts\NavigationCondition::class => [
                \App\Services\Accounting\Navigation\LocalLedgerCondition::class,
            ],
        ];
    }
}
