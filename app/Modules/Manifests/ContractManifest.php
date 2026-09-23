<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Vertragsverwaltung & Fristen“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ContractManifest extends Manifest {
    public function code(): string {
        return 'contract';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Vertragsverwaltung & Fristen';
    }

    public function licenseCode(): string {
        return 'module.contracts';
    }

    public function description(): string {
        return 'Allgemeine Vertragsakten beliebiger Art mit Laufzeit-/Verlängerungslogik, Kündigungsfrist, Indexierungsregel und Obligationen-/Vertragskalender.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Contract',
            'Contracts',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'contract_obligations',
            'contract_signature_evidences',
            'contract_signature_links',
            'contract_signature_requests',
            'contract_signing_manifest_items',
            'contract_signing_revisions',
            'contracts',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'contracts.*',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Contracts,
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'contracts.index',
            ],
            'groups' => [],
        ];
    }
}
