<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Kunden und Kundenportal“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CustomerManifest extends Manifest {
    public function code(): string {
        return 'customer';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Kunden und Kundenportal';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Customer',
            'Customers',
            'CustomerPortal',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'customer_circular_recipients',
            'customer_circulars',
            'customer_geofences',
            'customer_merge_dismissals',
            'customer_queries',
            'customers',
            'foreign_customers',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Customers,
            PermissionGroup::CustomerPortal,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Customer\Import\ContactPersonSpec::class,
                \App\Services\Customer\Import\CustomerSpec::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Customer\Retention\CustomerRetentionPolicies::class,
            ],
        ];
    }
}
