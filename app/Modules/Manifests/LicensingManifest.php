<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicensingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Lizenzierung und Modulfreischaltung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class LicensingManifest extends Manifest {
    public function code(): string {
        return 'licensing';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Lizenzierung und Modulfreischaltung';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Licensing',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'feature_usage_counters',
            'license_flag_overrides',
            'plan_module_grace',
        ];
    }
}
