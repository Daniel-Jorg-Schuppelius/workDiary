<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * GitLab-Issues-Plugin (Feature 060, MVP-129, Bauturbo A6). Die eigentliche
 * Anbindung (Instanz-URL, Projekt-ID, Token, Webhook-Token) liegt PRO
 * ORGANISATION verschlüsselt in `plugin_settings` (Auto-Form der Plugin-Karte).
 * ENV dient nur als globaler Fallback für Tests/Konsole.
 *
 * GitLab-REST-API v4: Auth `PRIVATE-TOKEN` (Project Access Token empfohlen),
 * `GET /api/v4/projects/{id}/issues` (`updated_after`, per_page ≤ 100).
 * Issues werden über `iid` + Projekt identifiziert — NIE über die globale
 * `id` (Recherche 2026-07). Webhook klassisch über den statischen
 * `X-Gitlab-Token`-Header.
 */

return [
    'enabled' => env('GITLAB_ENABLED', false),
    'base_url' => env('GITLAB_BASE_URL', 'https://gitlab.com'),
    'api_token' => env('GITLAB_API_TOKEN'),
    'project_id' => env('GITLAB_PROJECT_ID'),
    // On-Premise-Instanzen im eigenen Netz brauchen die ausdrückliche
    // Freigabe privater Adressen (SSRF-Leitplanke, Muster JTL-Wawi).
    'allow_private_network' => env('GITLAB_ALLOW_PRIVATE_NETWORK', false),
    // Seiten-Obergrenze je Polling-Lauf (à 100 Issues); Rest holt der nächste Lauf.
    'max_pages' => env('GITLAB_SYNC_MAX_PAGES', 10),
];
