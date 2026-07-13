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
 * SharePoint-Ablage-Plugin (MVP-330, Bauturbo A10). Spiegelt freigegebene
 * Dokumente über Microsoft Graph in eine SharePoint-Dokumentbibliothek —
 * WebDAV gegen SharePoint Online ist tot (Legacy-Auth abgeschaltet), daher
 * Graph: PUT …:/content (klein) bzw. createUploadSession (Chunks ≥ 4 MB).
 *
 * OAuth2 Authorization-Code + PKCE (delegated) nach A8-Muster; Scope-Default
 * `Files.ReadWrite.All offline_access`. Für Least Privilege stattdessen
 * SHAREPOINT_SCOPES="offline_access Sites.Selected" setzen — der Consent
 * allein gibt dann KEINEN Zugriff, der Tenant-Admin grantet je Site
 * (POST /sites/{id}/permissions, roles read/write).
 *
 * Client-ID/-Secret/Tenant sind INSTALLATIONS-weit (ENV) — nie je
 * Organisation. Ohne eigene SHAREPOINT_*-Werte greifen die MSGRAPH_*-Werte
 * (eine App-Registrierung für Kalender + Dateiablage).
 * Externe Hürde (Welle C): App-Registrierung/Consent bzw. Sites.Selected-
 * Grants im Ziel-Tenant.
 */

return [
    'enabled' => env('SHAREPOINT_ENABLED', false),
    'client_id' => env('SHAREPOINT_CLIENT_ID', ''),
    'client_secret' => env('SHAREPOINT_CLIENT_SECRET', ''),
    'tenant' => env('SHAREPOINT_TENANT', ''),
    'api_base' => env('SHAREPOINT_API_BASE', 'https://graph.microsoft.com/v1.0'),
    'authorize_url' => env('SHAREPOINT_AUTHORIZE_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize'),
    'token_url' => env('SHAREPOINT_TOKEN_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'),
    'scopes' => env('SHAREPOINT_SCOPES', 'offline_access Files.ReadWrite.All'),
];
