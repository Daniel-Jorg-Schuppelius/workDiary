<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Konfiguration je Organisation. Endpunkte laut OpenID-Konfiguration von DATEV
 * (`login.datev.de/openid[sandbox]/.well-known/openid-configuration`).
 */
class DatevOnlineConfig {
    public const SCOPES = 'openid offline_access datev:accounting:clients datev:accounting:documents datev:accounting:extf-files-import';

    /** @return array{enabled: bool, client_id: string, client_secret: string, sandbox: bool, authorize_url: string, token_url: string, revoke_url: string, scopes: string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(DatevOnlinePlugin::ID, $organizationId);
        $sandbox = $r->bool('sandbox', true);

        return [
            'enabled' => $r->enabled(),
            'client_id' => (string) $r->string('client_id', trim: true),
            'client_secret' => (string) $r->string('client_secret', trim: true),
            'sandbox' => $sandbox,
            'authorize_url' => $sandbox ? 'https://login.datev.de/openidsandbox/authorize' : 'https://login.datev.de/openid/authorize',
            'token_url' => $sandbox ? 'https://sandbox-api.datev.de/token' : 'https://api.datev.de/token',
            'revoke_url' => $sandbox ? 'https://sandbox-api.datev.de/revoke' : 'https://api.datev.de/revoke',
            'scopes' => self::SCOPES,
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
