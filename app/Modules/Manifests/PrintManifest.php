<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Druck und Etiketten“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class PrintManifest extends Manifest {
    public function code(): string {
        return 'print';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Druck und Etiketten';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Print',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'label_templates',
            'print_orders',
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Services\Print\Contracts\ProductionOrderFactory::class => \App\Services\Print\Contracts\NullProductionOrderFactory::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Navigation\Contracts\NavigationCondition::class => [
                \App\Services\Print\Navigation\PrintProfileCondition::class,
            ],
        ];
    }
}
