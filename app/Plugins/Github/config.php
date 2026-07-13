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
 * GitHub-Issues-Plugin (Feature 060, MVP-129, Bauturbo A6). Die eigentliche
 * Anbindung (owner/repo, Fine-grained PAT, Webhook-Secret) liegt PRO
 * ORGANISATION verschlüsselt in `plugin_settings` (Auto-Form der Plugin-Karte).
 * ENV dient nur als globaler Fallback für Tests/Konsole.
 *
 * GitHub-REST-API v3: `Accept: application/vnd.github+json` +
 * `X-GitHub-Api-Version`-Header, Auth `Authorization: Bearer <PAT>`;
 * `GET /repos/{owner}/{repo}/issues` liefert AUCH Pull Requests
 * (`pull_request`-Schlüssel) — der Importer filtert sie. Webhook signiert per
 * `X-Hub-Signature-256: sha256=HMAC(body)`.
 */

return [
    'enabled' => env('GITHUB_ENABLED', false),
    'base_url' => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
    'api_version' => env('GITHUB_API_VERSION', '2022-11-28'),
    'api_token' => env('GITHUB_API_TOKEN'),
    'repo_owner' => env('GITHUB_REPO_OWNER'),
    'repo_name' => env('GITHUB_REPO_NAME'),
    // Seiten-Obergrenze je Polling-Lauf (à 100 Issues); Rest holt der nächste Lauf.
    'max_pages' => env('GITHUB_SYNC_MAX_PAGES', 10),
];
