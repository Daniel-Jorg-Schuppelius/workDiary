<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Leasing & Asset-Verträge“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AssetFinanceManifest extends Manifest {
    public function code(): string {
        return 'asset_finance';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Leasing & Asset-Verträge';
    }

    public function licenseCode(): string {
        return 'module.asset_finance';
    }

    public function description(): string {
        return 'Leasing- und Finanzierungsakten mit Konditionen-Snapshot, Fristenkalender, Nutzungslimits und Soll-Ist-Sicht.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'AssetFinance',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'asset_finance_contract_assets',
            'asset_finance_contracts',
            'asset_finance_cost_snapshots',
            'asset_finance_deadlines',
            'asset_finance_end_processes',
            'asset_finance_options',
            'asset_finance_rate_schedules',
            'asset_finance_report_snapshots',
            'asset_finance_terms',
            'asset_finance_usage_limits',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'asset-finance.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::AssetFinance,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'asset-finance.index',
                'asset-finance.deadlines.index',
                'asset-finance.reports.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\AssetFinance\Demo\AssetFinanceDemoBlock::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\AssetFinance\DeadlineScans\AssetFinanceDeadlineScan::class,
            ],
        ];
    }
}
