<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „SSO & Verzeichnisdienste“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class SsoManifest extends Manifest {
    public function code(): string {
        return 'sso';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'SSO & Verzeichnisdienste';
    }

    public function licenseCode(): string {
        return 'module.sso';
    }

    public function description(): string {
        return 'Single-Sign-on und Verzeichnisdienste (SCIM-Provisionierung, OIDC-SSO, SAML 2.0).';
    }

    /** @return list<string> */
    public function folders(): array {
        return [];
    }

    /** @return list<string> */
    public function tables(): array {
        return [];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'admin.sso.*',
        ];
    }
}
