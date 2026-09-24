<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „ISMS“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class IsmsManifest extends Manifest {
    public function code(): string {
        return 'isms';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'ISMS';
    }

    public function licenseCode(): string {
        return 'module.isms';
    }

    public function description(): string {
        return 'Informationssicherheits-Managementsystem (ISO 27001).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Isms',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'isms_advisories',
            'isms_applicability_statements',
            'isms_assessment_snapshots',
            'isms_audit_findings',
            'isms_audit_package_tokens',
            'isms_audit_packages',
            'isms_audit_programs',
            'isms_audits',
            'isms_certificates',
            'isms_control_requirement',
            'isms_control_risk',
            'isms_controls',
            'isms_corrective_actions',
            'isms_incident_control',
            'isms_incident_risk',
            'isms_management_reviews',
            'isms_norm_statuses',
            'isms_requirements',
            'isms_risk_assessments',
            'isms_risks',
            'isms_scopes',
            'isms_security_incidents',
            'isms_software_installations',
            'isms_software_products',
            'isms_supplier_assessments',
            'isms_vulnerabilities',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'isms.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Isms,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'isms',
            ],
            'items' => [],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Isms\DeadlineScans\IsmsDeadlineScans::class,
            ],
        ];
    }
}
