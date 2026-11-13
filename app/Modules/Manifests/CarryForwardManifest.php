<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645): Baustein der
 * Plattform. Träger implementieren `App\Models\Contracts\PeriodicOccurrence`
 * und binden `<x-carry-forward-notes :subject>` ein; eigene Rechte gibt es
 * nicht, es gelten die des Trägers.
 */
final class CarryForwardManifest extends Manifest {
    public function code(): string {
        return 'carry-forward';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Merker für die nächste Periode';
    }

    public function description(): string {
        return 'Hinweise an einer wiederkehrenden Reihe, die beim nächsten Vorkommen erscheinen und dort abgehakt werden.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'CarryForward',
        ];
    }

    /** @return list<string> Nur zur Doku: Plattformrouten sind nicht lizenzgebunden. */
    public function routePatterns(): array {
        return [
            'carry-forward-notes.*',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'carry_forward_notes',
        ];
    }
}
