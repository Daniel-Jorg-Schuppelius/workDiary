<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Microsoft-365-Kalender-Plugin (MVP-328, Bauturbo A8). Nur-Publish-Pilot:
 * WorkDiary-Termine → Microsoft Graph (POST/PATCH/DELETE /me/events bzw.
 * /me/calendars/{id}/events), OAuth2 Authorization-Code + PKCE gegen die
 * Microsoft Identity Platform (delegated). Scope Calendars.ReadWrite +
 * offline_access (Refresh-Token); KEINE weiteren Graph-Scopes.
 *
 * Client-ID/-Secret und Tenant sind INSTALLATIONS-weit (ENV) — nie je
 * Organisation. Tenant-Default 'common' (Multi-Tenant-App); für
 * Single-Tenant-Registrierungen die Verzeichnis-ID (GUID) setzen.
 * Externe Hürde (Welle C): App-Registrierung/Admin-Consent im Ziel-Tenant.
 */

return [
    'enabled' => env('MSGRAPH_ENABLED', false),
    'client_id' => env('MSGRAPH_CLIENT_ID', ''),
    'client_secret' => env('MSGRAPH_CLIENT_SECRET', ''),
    'tenant' => env('MSGRAPH_TENANT', 'common'),
    'api_base' => env('MSGRAPH_API_BASE', 'https://graph.microsoft.com/v1.0'),
    'authorize_url' => env('MSGRAPH_AUTHORIZE_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize'),
    'token_url' => env('MSGRAPH_TOKEN_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'),
    'scopes' => 'offline_access Calendars.ReadWrite',
    // Cloud-Dokumenteingang (Feature 080): eigene lesende Verbindung, getrennt vom Kalender.
    // Files.Read.All (eigene + geteilte Drives) + Sites.Read.All (SharePoint); engere Scopes = Enterprise-Ausbau.
    'intake_scopes' => env('MSGRAPH_INTAKE_SCOPES', 'offline_access Files.Read.All Sites.Read.All'),
    // Seitengröße des Delta-Laufs.
    'intake_page_size' => (int) env('MSGRAPH_INTAKE_PAGE_SIZE', 200),
    // Cloud-Backupziel (Feature 017 Phase 32): eigene systemweite Verbindung. Files.ReadWrite ist die engste
    // produktiv verfügbare delegierte Berechtigung — nur für ein bestätigtes Integrationskonto (Konzept §Bedrohungsmodell).
    'backup_scopes' => env('MSGRAPH_BACKUP_SCOPES', 'offline_access User.Read Files.ReadWrite'),
];
