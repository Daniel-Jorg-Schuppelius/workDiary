<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Reisen & Spesen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class TravelManifest extends Manifest {
    public function code(): string {
        return 'travel';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Reisen & Spesen';
    }

    public function licenseCode(): string {
        return 'module.spesen';
    }

    public function description(): string {
        return 'Reisekosten, Spesen und Belegerfassung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Travel',
            'Expense',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'expense_categories',
            'expenses',
            'per_diem_days',
            'per_diem_rates',
            'per_diem_trips',
            'travel_logs',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'travel-logs.*',
            'expenses.*',
            'per-diem-trips.*',
            'expense-approvals.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'travel-expenses',
            ],
            'items' => [],
            'groups' => [],
        ];
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array {
        return [
            \App\Services\Routing\Contracts\TravelLogRecorder::class => \App\Services\Travel\TravelLogService::class,
        ];
    }
}
