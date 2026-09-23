<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Anmeldung, Sicherheit, SSO/SCIM“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AuthManifest extends Manifest {
    public function code(): string {
        return 'auth';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Anmeldung, Sicherheit, SSO/SCIM';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Auth',
            'Security',
            'Scim',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'personal_access_tokens',
            'scim_groups',
            'scim_tokens',
            'security_events',
            'sso_connections',
            'sso_identities',
            'two_factor_credentials',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Access,
        ];
    }
}
