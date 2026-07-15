<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph;

/**
 * Installationsweite Microsoft-365-Konfiguration (MVP-328, Bauturbo A8):
 * Client-ID/-Secret + Tenant kommen ausschließlich aus ENV/config — NIE je
 * Organisation (die per-Org-Daten liegen in `msgraph_connections`).
 * Der Tenant-Platzhalter in den Endpunkt-URLs wird hier aufgelöst
 * (Default 'common' = Multi-Tenant).
 */
class MsgraphConfig {
    /** @return array{client_id: string, client_secret: string, tenant: string, api_base: string, authorize_url: string, token_url: string, scopes: string, intake_scopes: string, intake_page_size: int, backup_scopes: string} */
    public static function resolve(): array {
        $tenant = trim((string) config('plugins.msgraph.tenant', 'common')) ?: 'common';

        return [
            'client_id' => (string) config('plugins.msgraph.client_id', ''),
            'client_secret' => (string) config('plugins.msgraph.client_secret', ''),
            'tenant' => $tenant,
            'api_base' => rtrim((string) config('plugins.msgraph.api_base', 'https://graph.microsoft.com/v1.0'), '/'),
            'authorize_url' => str_replace('{tenant}', $tenant, (string) config('plugins.msgraph.authorize_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize')),
            'token_url' => str_replace('{tenant}', $tenant, (string) config('plugins.msgraph.token_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token')),
            'scopes' => (string) config('plugins.msgraph.scopes', 'offline_access Calendars.ReadWrite'),
            // Cloud-Dokumenteingang (Feature 080, MVP-354).
            'intake_scopes' => (string) config('plugins.msgraph.intake_scopes', 'offline_access Files.Read.All Sites.Read.All'),
            'intake_page_size' => (int) config('plugins.msgraph.intake_page_size', 200),
            // Cloud-Backupziel (Feature 017 Phase 32, MVP-363).
            'backup_scopes' => (string) config('plugins.msgraph.backup_scopes', 'offline_access User.Read Files.ReadWrite'),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
