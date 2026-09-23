<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Datenschutz“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class PrivacyManifest extends Manifest {
    public function code(): string {
        return 'privacy';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Datenschutz';
    }

    public function licenseCode(): string {
        return 'module.datenschutz';
    }

    public function description(): string {
        return 'Datenschutzmanagement (VVT, AVV, Betroffenenrechte).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Privacy',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'legal_holds',
            'privacy_agreement_activity',
            'privacy_attachments',
            'privacy_compliance_findings',
            'privacy_data_subject_requests',
            'privacy_dpia_steps',
            'privacy_dpias',
            'privacy_dsar_portals',
            'privacy_gvv_activity',
            'privacy_incident_events',
            'privacy_incidents',
            'privacy_joint_controller_agreements',
            'privacy_measure_assignments',
            'privacy_measure_reviews',
            'privacy_measures',
            'privacy_processing_activities',
            'privacy_processing_activity_versions',
            'privacy_processing_agreements',
            'privacy_processors',
            'privacy_request_events',
            'privacy_requirements',
            'privacy_subprocessors',
            'privacy_technical_measure_versions',
            'privacy_technical_measures',
            'retention_proposals',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'dataprotection.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [
                'datenschutz',
            ],
            'items' => [],
            'groups' => [],
        ];
    }
}
