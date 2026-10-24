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
 * Jira-Cloud-Aufgabenimport (Feature 169, Phase 157). OAuth 2.0 (3LO) über
 * EINE vom Herausgeber registrierte, verteilbare Atlassian-App — Client-ID
 * und Secret sind installationsweit, Kunden legen weder API-Tokens noch eigene
 * Apps an. Rotierende Refresh-Tokens; Lesen über
 * https://api.atlassian.com/ex/jira/{cloudId} und die Enhanced Search
 * (/rest/api/3/search/jql mit nextPageToken). Endpunkte und Scopes sind
 * bewusst config-only.
 */
return [
    'enabled' => env('JIRA_ENABLED', false),
    'client_id' => env('JIRA_CLIENT_ID', ''),
    'client_secret' => env('JIRA_CLIENT_SECRET', ''),
    'authorize_url' => 'https://auth.atlassian.com/authorize',
    'token_url' => 'https://auth.atlassian.com/oauth/token',
    'api_base' => 'https://api.atlassian.com',
    'scopes' => 'read:jira-work offline_access',

    // Seitengröße der Suche (Jira-Maximum mit Feldern: 100) und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('JIRA_PAGE_SIZE', 100),
    'max_pages' => (int) env('JIRA_MAX_PAGES', 50),
];
