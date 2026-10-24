<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Asana-Aufgabenimport (Feature 169, Phase 157). OAuth Authorization Code mit
 * PKCE (S256) über die installationsweite Asana-App, nur Lesescopes; keine
 * Personal Access Tokens. REST 1.0 unter https://app.asana.com/api/1.0,
 * Seiten über `next_page.offset`, 429 mit Retry-After übernimmt das Toolkit.
 * Endpunkte und Scopes sind bewusst config-only.
 */
return [
    'enabled' => env('ASANA_ENABLED', false),
    'client_id' => env('ASANA_CLIENT_ID', ''),
    'client_secret' => env('ASANA_CLIENT_SECRET', ''),
    'authorize_url' => 'https://app.asana.com/-/oauth_authorize',
    'token_url' => 'https://app.asana.com/-/oauth_token',
    'revoke_url' => 'https://app.asana.com/-/oauth_revoke',
    'api_base' => 'https://app.asana.com/api/1.0',
    'scopes' => 'tasks:read projects:read workspaces:read users:read',

    // Seitengröße (Asana-Maximum: 100) und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('ASANA_PAGE_SIZE', 100),
    'max_pages' => (int) env('ASANA_MAX_PAGES', 50),
];
