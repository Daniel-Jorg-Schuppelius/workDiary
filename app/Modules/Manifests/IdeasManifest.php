<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeasManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Ideenlandkarten“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class IdeasManifest extends Manifest {
    public function code(): string {
        return 'ideas';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Ideenlandkarten';
    }

    public function licenseCode(): string {
        return 'module.ideas';
    }

    public function description(): string {
        return 'Private und gemeinsame Ideenlandkarten (Mindmaps) mit Überführung in Aufgaben, Projekte und Wissen.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Ideas',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'idea_map_shares',
            'idea_maps',
            'idea_node_links',
            'idea_node_summaries',
            'idea_nodes',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'ideas.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Ideas,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'ideas.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function requires(): array {
        return [
            'knowledge',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Ideas\Retention\IdeasRetentionPolicies::class,
            ],
        ];
    }
}
