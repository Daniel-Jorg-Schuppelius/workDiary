<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Cloud-Dokumentenimport“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CloudIntakeManifest extends Manifest {
    public function code(): string {
        return 'cloud_intake';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Cloud-Dokumentenimport';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'CloudIntake',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'cloud_document_connections',
            'cloud_document_items',
            'cloud_document_routes',
        ];
    }
}
