<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntraIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Sso;

/**
 * Entra-ID-Spezifika der SSO-Härtung (Feature 057-Ausbau, MS365-Plan G1):
 *
 * - Multi-Tenant-Endpunkte (`common`/`organizations`/`consumers`) liefern den
 *   Issuer nur als Template — ohne Tenant-Allowlist passiert jedes signierte
 *   Token aus IRGENDEINEM Entra-Tenant die Prüfung. WorkDiary verlangt daher
 *   je Verbindung den TENANT-SPEZIFISCHEN Issuer (GUID im Pfad).
 * - Der `email`-Claim ist bei Entra weder verifiziert noch stabil und in
 *   Fremd-Tenants frei setzbar (nOAuth-Angriff, Full Account Takeover) —
 *   E-Mail-Account-Linking ist für Entra-Verbindungen deshalb gesperrt.
 */
final class EntraIssuer {
    private const HOSTS = ['login.microsoftonline.com', 'sts.windows.net'];

    private const MULTI_TENANT_SEGMENTS = ['common', 'organizations', 'consumers'];

    public static function isEntra(string $issuer): bool {
        $host = strtolower((string) parse_url($issuer, PHP_URL_HOST));

        return in_array($host, self::HOSTS, true);
    }

    /** Erster Pfadabschnitt muss die Tenant-GUID sein (nie common/organizations/consumers). */
    public static function isTenantSpecific(string $issuer): bool {
        $path = trim((string) parse_url($issuer, PHP_URL_PATH), '/');
        $first = strtolower(explode('/', $path)[0] ?? '');

        if (in_array($first, self::MULTI_TENANT_SEGMENTS, true)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $first) === 1;
    }
}
