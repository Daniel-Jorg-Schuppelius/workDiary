<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoProviderType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anbieter-Typ einer OIDC-SSO-Verbindung (Feature 057-Ausbau): erlaubt neben
 * beliebigen Standard-OIDC-Providern (`custom`) die bequemen Presets für
 * Microsoft 365 (Entra ID) und Google Workspace. Das Preset füllt Issuer und
 * Scopes vor; die eigentliche Authentifizierung läuft unverändert über den
 * OIDC-Flow gegen den jeweiligen Anbieter. `custom` bleibt der neutrale
 * Ausgangszustand (Bestandsverbindungen).
 */
enum SsoProviderType: string implements HasLabel {
    use HasOptions;

    case Custom = 'custom';
    case Microsoft = 'microsoft';
    case Google = 'google';

    /** Fester Google-OIDC-Issuer (Discovery unter /.well-known/openid-configuration). */
    public const GOOGLE_ISSUER = 'https://accounts.google.com';

    public function label(): string {
        return match ($this) {
            self::Custom => __('sso.provider.custom'),
            self::Microsoft => __('sso.provider.microsoft'),
            self::Google => __('sso.provider.google'),
        };
    }

    /** Verlangt der Anbieter eine Tenant-Angabe (Microsoft: GUID/Domain im Issuer-Pfad)? */
    public function requiresTenant(): bool {
        return $this === self::Microsoft;
    }

    /** Preset-Anbieter (Issuer/Scopes werden vorgegeben) — im Gegensatz zu `custom`. */
    public function isPreset(): bool {
        return $this !== self::Custom;
    }

    /**
     * Baut den tenant-spezifischen Issuer eines Preset-Anbieters. Microsoft
     * verlangt den Tenant (GUID oder verifizierte Domain) — ohne ihn gibt es
     * keinen gültigen (tenant-spezifischen) Issuer, siehe {@see EntraIssuer}.
     * `custom` liefert nie ein Preset (null → Admin trägt den Issuer selbst ein).
     */
    public function presetIssuer(?string $tenant = null): ?string {
        return match ($this) {
            self::Custom => null,
            self::Google => self::GOOGLE_ISSUER,
            self::Microsoft => ($t = trim((string) $tenant)) !== ''
                ? 'https://login.microsoftonline.com/' . rawurlencode($t) . '/v2.0'
                : null,
        };
    }

    /** Empfohlene Default-Scopes; `openid` wird ohnehin erzwungen ({@see SsoConnection::scopeList()}). */
    public function presetScopes(): string {
        return 'openid profile email';
    }
}
