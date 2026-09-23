<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Projekte, Aufgaben, Meilensteine“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ProjectManifest extends Manifest {
    public function code(): string {
        return 'project';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Projekte, Aufgaben, Meilensteine';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Project',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'milestones',
            'project_billing_rules',
            'project_merge_dismissals',
            'project_team',
            'project_user',
            'projects',
            'task_user',
            'tasks',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Projects,
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'openproject',
        ];
    }
}
