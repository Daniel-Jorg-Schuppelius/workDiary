<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Reklamation & Gewährleistung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ClaimsManifest extends Manifest {
    public function code(): string {
        return 'claims';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Reklamation & Gewährleistung';
    }

    public function licenseCode(): string {
        return 'module.claims';
    }

    public function description(): string {
        return 'Reklamationsakten mit Bewertung, Entscheidung, RMA-Rückläufern, Maßnahmen, kaufmännischen Folgen und Lieferantenregress.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Claims',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'claim_actions',
            'claim_assessments',
            'claim_case_links',
            'claim_cases',
            'claim_decisions',
            'claim_evidence',
            'claim_financial_outcomes',
            'claim_inspections',
            'claim_report_snapshots',
            'claim_rma_returns',
            'claim_supplier_recourses',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'claims.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Claims,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'claims.index',
                'claims.reports.index',
            ],
            'groups' => [],
        ];
    }
}
