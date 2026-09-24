<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Altgeräte-Entsorgung & Nachweise“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DisposalManifest extends Manifest {
    public function code(): string {
        return 'disposal';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Altgeräte-Entsorgung & Nachweise';
    }

    public function licenseCode(): string {
        return 'module.entsorgung';
    }

    public function description(): string {
        return 'Entsorgungsakten für Altgeräte: Geräteliste mit AVV-Schlüsseln, Datenträger-Behandlung nach DIN 66399, Entsorger-Übergabe und prüffester Kundennachweis im Portal.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Disposal',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'data_media_treatments',
            'disposal_handovers',
            'disposal_items',
            'disposal_job_events',
            'disposal_jobs',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'disposal.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Disposal,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'disposal.index',
                'disposal.reports.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Disposal\Demo\DisposalDemoBlock::class,
            ],
        ];
    }
}
