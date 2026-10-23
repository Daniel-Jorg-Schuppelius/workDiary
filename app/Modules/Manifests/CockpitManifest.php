<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CockpitManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/**
 * Entitäts-Cockpits (Feature 182): Erweiterungspunkt `CockpitPanel` — Module
 * liefern ihre Panels an Kunde, Lieferant, Projekt, Lead und Asset.
 */
final class CockpitManifest extends Manifest {
    public function code(): string {
        return 'cockpit';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Entitäts-Cockpits';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Cockpit',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [];
    }
}
