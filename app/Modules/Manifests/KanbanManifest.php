<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanbanManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Kanban“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class KanbanManifest extends Manifest {
    public function code(): string {
        return 'kanban';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Kanban';
    }

    public function licenseCode(): string {
        return 'module.kanban';
    }

    public function description(): string {
        return 'Aufgaben als Kanban-Board organisieren.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [];
    }

    /** @return list<string> */
    public function tables(): array {
        return [];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'kanban.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'kanban.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'todoist',
        ];
    }
}
