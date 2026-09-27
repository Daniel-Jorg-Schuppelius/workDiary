<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/**
 * Modul „Schadensfälle“ (MVP-919): Kernbaustein für Versicherungs- und
 * Schadensfälle; Verleih, Leasing, Reklamation und Fuhrpark hängen ihre Akten
 * als Träger an. Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen
 * `php artisan modules:check`.
 */
final class DamageManifest extends Manifest {
    public function code(): string {
        return 'damage';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Schadensfälle';
    }

    public function description(): string {
        return 'Versicherungs- und Schadensfälle mit Regulierung, Selbstbehalt und Verlauf an Verleih, Leasing, Reklamation und Fahrzeug.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Damage',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'damage_case_events',
            'damage_cases',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Damage,
        ];
    }
}
