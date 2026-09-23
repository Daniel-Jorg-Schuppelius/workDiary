<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceDeskManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Service Desk (ITSM)“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ServiceDeskManifest extends Manifest {
    public function code(): string {
        return 'service_desk';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Service Desk (ITSM)';
    }

    public function licenseCode(): string {
        return 'module.service_desk';
    }

    public function description(): string {
        return 'Servicekatalog, Requests mit Genehmigungen, Incident/Problem/Change (setzt Helpdesk voraus).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'business_services',
            'service_offerings',
            'service_queues',
            'service_requests',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'servicedesk.*',
        ];
    }

    /** @return list<string> */
    public function requires(): array {
        return [
            'helpdesk',
        ];
    }
}
