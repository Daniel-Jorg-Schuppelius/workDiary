<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Agiles Projektmanagement“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AgileManifest extends Manifest {
    public function code(): string {
        return 'agile';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Agiles Projektmanagement';
    }

    public function licenseCode(): string {
        return 'module.agile_projects';
    }

    public function description(): string {
        return 'Produkt-Backlog, Projektboards (Kanban/Scrum), Sprints und agile Berichte je Projekt.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Agile',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'agile_acceptance_criteria',
            'agile_board_columns',
            'agile_boards',
            'agile_events',
            'agile_sprint_items',
            'agile_sprints',
            'agile_work_items',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'agile.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'agile.reports.overview',
            ],
            'groups' => [],
        ];
    }
}
