<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Sicherung und Cloudspeicher“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class BackupManifest extends Manifest {
    public function code(): string {
        return 'backup';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Sicherung und Cloudspeicher';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Backup',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'backup_generation_parts',
            'backup_generations',
            'backup_heartbeats',
            'backup_target_connections',
            'restore_tests',
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'nextcloud',
            'dropbox',
            'google-drive',
            'sharepoint',
            'webdav',
        ];
    }
}
