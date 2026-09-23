<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Anlagen, Wartung, Zähler, Software“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AssetManifest extends Manifest {
    public function code(): string {
        return 'asset';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Anlagen, Wartung, Zähler, Software';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Asset',
            'MeterReading',
            'Software',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'asset_assignments',
            'asset_block_exceptions',
            'asset_blocks',
            'asset_components',
            'asset_defects',
            'asset_measurement_values',
            'asset_ownership_changes',
            'assets',
            'energy_logs',
            'maintenance_plan_templates',
            'maintenance_plans',
            'maintenance_windows',
            'meter_readings',
            'permits',
            'software',
            'software_installations',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::MasterData,
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'remote-support',
            'teamviewer',
            'anydesk',
        ];
    }
}
