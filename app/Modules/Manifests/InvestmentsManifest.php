<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentsManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Investitionsplanung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class InvestmentsManifest extends Manifest {
    public function code(): string {
        return 'investments';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Investitionsplanung';
    }

    public function licenseCode(): string {
        return 'module.investments';
    }

    public function description(): string {
        return 'Investitionsakten mit Varianten, Budgetantrag, Freigabekette und Soll-Ist-Verfolgung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Investments',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'investment_actuals',
            'investment_budget_requests',
            'investment_cases',
            'investment_deviations',
            'investment_links',
            'investment_options',
            'investment_reviews',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'investments.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'investments.index',
            ],
            'groups' => [],
        ];
    }
}
