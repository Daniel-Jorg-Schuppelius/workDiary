<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint;

/**
 * Installationsweite SharePoint-Konfiguration (MVP-330, Bauturbo A10):
 * Client-ID/-Secret + Tenant kommen ausschließlich aus ENV/config — NIE je
 * Organisation (die per-Org-Daten liegen in `sharepoint_connections`).
 *
 * Ohne eigene SHAREPOINT_*-Werte greifen die MSGRAPH_*-Werte des
 * Kalender-Plugins (A8): EINE Azure-App-Registrierung kann beide Scopes
 * (Calendars.ReadWrite + Files.ReadWrite.All) und beide Redirect-URIs
 * tragen — nur der Scope-Satz ist zwingend plugin-eigen. Für das
 * Least-Privilege-Modell `Sites.Selected` (Site-Grants je
 * `POST /sites/{id}/permissions` durch den Tenant-Admin) den
 * SHAREPOINT_SCOPES-Wert entsprechend setzen.
 */
class SharepointConfig {
    /** @return array{client_id: string, client_secret: string, tenant: string, api_base: string, authorize_url: string, token_url: string, scopes: string} */
    public static function resolve(): array {
        $tenant = trim((string) (config('plugins.sharepoint.tenant') ?: config('plugins.msgraph.tenant', 'common'))) ?: 'common';

        return [
            'client_id' => (string) (config('plugins.sharepoint.client_id') ?: config('plugins.msgraph.client_id', '')),
            'client_secret' => (string) (config('plugins.sharepoint.client_secret') ?: config('plugins.msgraph.client_secret', '')),
            'tenant' => $tenant,
            'api_base' => rtrim((string) config('plugins.sharepoint.api_base', 'https://graph.microsoft.com/v1.0'), '/'),
            'authorize_url' => str_replace('{tenant}', $tenant, (string) config('plugins.sharepoint.authorize_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize')),
            'token_url' => str_replace('{tenant}', $tenant, (string) config('plugins.sharepoint.token_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token')),
            'scopes' => (string) config('plugins.sharepoint.scopes', 'offline_access Files.ReadWrite.All'),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
