<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Todoist-Plugin (Feature 055). Zentrale API-Verifikation (Stand 2026-07-03):
 * einheitliche API v1 unter https://api.todoist.com/api/v1 (cursor-basierte
 * Pagination), OAuth über todoist.com/oauth (Access-Token ohne Ablauf, kein
 * Refresh-Token — Schema hält beides aus), Scope data:read_write
 * (data:delete/project:delete werden bewusst NICHT angefordert), Webhook-
 * Signatur X-Todoist-Hmac-SHA256 = HMAC-SHA256(raw body, client_secret),
 * Prioritätsadapter: API-Wert 4 = höchste (UI "p1").
 *
 * Client-ID/-Secret sind INSTALLATIONS-weit (ENV) — nie je Organisation.
 */

return [
    'enabled' => env('TODOIST_ENABLED', false),
    'client_id' => env('TODOIST_CLIENT_ID', ''),
    'client_secret' => env('TODOIST_CLIENT_SECRET', ''),
    'api_base' => env('TODOIST_API_BASE', 'https://api.todoist.com/api/v1'),
    'authorize_url' => env('TODOIST_AUTHORIZE_URL', 'https://todoist.com/oauth/authorize'),
    'token_url' => env('TODOIST_TOKEN_URL', 'https://todoist.com/oauth/access_token'),
    'scopes' => 'data:read_write',
];
