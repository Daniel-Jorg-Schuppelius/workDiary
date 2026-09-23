<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashRegisterManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Kassenbuch“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CashRegisterManifest extends Manifest {
    public function code(): string {
        return 'cash_register';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Kassenbuch';
    }

    public function licenseCode(): string {
        return 'module.kasse';
    }

    public function description(): string {
        return 'GoBD-konformes Kassenbuch: Bareinnahmen/-ausgaben, Storno statt Löschen, Tagesabschluss mit Kassensturz (kein POS/TSE).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'cash_daily_closings',
            'cash_entries',
            'cash_registers',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'cash-registers.*',
        ];
    }
}
