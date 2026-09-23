<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Prüfmittel & Kalibrierung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AssetComplianceManifest extends Manifest {
    public function code(): string {
        return 'asset_compliance';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Prüfmittel & Kalibrierung';
    }

    public function licenseCode(): string {
        return 'module.asset_compliance';
    }

    public function description(): string {
        return 'Prüfprofile, Prüfpflichten, Prüfprotokolle, Kalibrierzertifikate und Einsatzsperren für prüfpflichtige Assets.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'AssetCompliance',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'asset_calibration_certificates',
            'asset_compliance_assignments',
            'asset_compliance_norm_references',
            'asset_compliance_profiles',
            'asset_compliance_report_snapshots',
            'asset_compliance_requirements',
            'asset_inspection_events',
            'asset_inspection_results',
            'asset_inspection_schedules',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'asset-compliance.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::AssetCompliance,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'asset-compliance.index',
                'asset-compliance.profiles.index',
                'asset-compliance.schedules.index',
                'asset-compliance.reports.index',
            ],
            'groups' => [],
        ];
    }
}
