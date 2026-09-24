<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DokumentdesignManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „PDF-Dokumentdesign & Firmenbogen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DokumentdesignManifest extends Manifest {
    public function code(): string {
        return 'dokumentdesign';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'PDF-Dokumentdesign & Firmenbogen';
    }

    public function licenseCode(): string {
        return 'module.dokumentdesign';
    }

    public function description(): string {
        return 'Firmenbogen, Druckbereiche, Informationsblöcke und Tabellenstil-Presets für erzeugte PDF-Dokumente.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'DocumentDesign',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'document_render_profile_versions',
            'document_render_profiles',
            'document_render_snapshots',
            'letterhead_assets',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'admin.document-design.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'admin.document-design.index',
            ],
            'groups' => [],
        ];
    }
}
