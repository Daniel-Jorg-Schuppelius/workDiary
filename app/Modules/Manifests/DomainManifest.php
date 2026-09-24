<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Domainverwaltung & DomainReselling“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DomainManifest extends Manifest {
    public function code(): string {
        return 'domain';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Domainverwaltung & DomainReselling';
    }

    public function licenseCode(): string {
        return 'module.domain';
    }

    public function description(): string {
        return 'Domainportfolio mit Kundenzuordnung, Registrierung, Kontakt-/Nameserver-/DNS-Pflege, Verlängerung/Transfer und Buchungsjournal (DomainReselling).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Domain',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'domain_accounting_entries',
            'domain_contact_projections',
            'domain_dns_record_projections',
            'domain_dns_zone_projections',
            'domain_events',
            'domain_external_invoices',
            'domain_projections',
            'domain_provider_commands',
            'domain_provider_connections',
            'domain_reseller_accounts',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'admin.domain-provider.*',
            'domains.*',
            'domain-reseller.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Domains,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'domains.index',
                'domain-reseller.index',
                'domains.reports',
                'admin.domain-provider.index',
            ],
            'groups' => [],
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'domainreselling',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Domain\DeadlineScans\DomainExpiryScan::class,
            ],
        ];
    }
}
