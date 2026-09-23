<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Formulare“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class FormManifest extends Manifest {
    public function code(): string {
        return 'form';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Formulare';
    }

    public function licenseCode(): string {
        return 'module.forms';
    }

    public function description(): string {
        return 'Formular- und Vorlagensystem.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Form',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'form_submissions',
            'form_templates',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'form-templates.*',
            'form-submissions.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Forms,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'form-submissions.index',
            ],
            'groups' => [],
        ];
    }
}
