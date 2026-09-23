<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Material und Verbrauch“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class MaterialManifest extends Manifest {
    public function code(): string {
        return 'material';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Material und Verbrauch';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Material',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'material_cost_allocations',
            'material_substitutes',
            'material_usages',
            'materials',
        ];
    }
}
