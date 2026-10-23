<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/**
 * Zentraler Arbeitsvorrat (Feature 179): Erweiterungspunkt `WorkItemSource`,
 * Liste und Dashboard-Kachel. Die Quellen melden die Fachmodule an.
 */
final class WorkQueueManifest extends Manifest {
    public function code(): string {
        return 'work-queue';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Arbeitsvorrat';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'WorkQueue',
        ];
    }

    /** @return list<string> Nur zur Doku: Plattformrouten sind nicht lizenzgebunden. */
    public function routePatterns(): array {
        return [
            'work-queue.*',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [];
    }
}
