<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationsManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Bewerbungen & Ausschreibungen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ApplicationsManifest extends Manifest {
    public function code(): string {
        return 'applications';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Bewerbungen & Ausschreibungen';
    }

    public function licenseCode(): string {
        return 'module.applications';
    }

    public function description(): string {
        return 'Auftragsbewerbungen/Ausschreibungsakten und Personalbewerbungen mit Vertragsverhandlung.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Applications',
            'Tenders',
            'Careers',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'application_contract_negotiations',
            'application_contract_reviews',
            'application_contract_versions',
            'application_opportunities',
            'application_requirements',
            'application_submissions',
            'job_application_documents',
            'job_application_interviews',
            'job_application_reviews',
            'job_application_uploads',
            'job_applications',
            'job_postings',
            'job_requisitions',
            'tender_competitor_bids',
            'tender_filter_profiles',
            'tender_notice_matches',
            'tender_notices',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'tenders.*',
            'tender-radar.*',
            'tenders.cockpit',
            'recruiting.*',
            'applications.*',
            'careers.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Applications,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'tenders.index',
                'tender-radar.index',
                'tenders.cockpit',
                'recruiting.requisitions.index',
                'recruiting.applications.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Applications\Demo\ApplicationsDemoBlock::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Applications\DeadlineScans\TenderDeadlineScan::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Applications\Retention\ApplicationsRetentionPolicies::class,
            ],
        ];
    }
}
