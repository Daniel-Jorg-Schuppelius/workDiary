<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Dokumente“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DocumentManifest extends Manifest {
    public function code(): string {
        return 'document';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Dokumente';
    }

    public function licenseCode(): string {
        return 'module.documents';
    }

    public function description(): string {
        return 'Dokumentenverwaltung mit Verträgen und Nachweisen.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Document',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'document_dispatches',
            'document_version_texts',
            'document_versions',
            'documents',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'documents.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Documents,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'documents.index',
            ],
            'groups' => [],
        ];
    }
}
