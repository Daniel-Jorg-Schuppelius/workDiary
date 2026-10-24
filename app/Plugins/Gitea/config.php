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
 * Gitea-/Forgejo-Aufgabenimport (Feature 171, Phase 158). Beide teilen die
 * REST-API unter <Basis-URL>/api/v1; Anmeldung über einen Access Token mit
 * Lesezugriff auf Repositories und Issues. Basis-URL und Token stehen je
 * Organisation in den Plugin-Einstellungen; interne Adressen nur mit dem
 * Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('GITEA_ENABLED', false),

    // Seitengröße (Gitea-Vorgabe MAX_RESPONSE_ITEMS: 50) und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('GITEA_PAGE_SIZE', 50),
    'max_pages' => (int) env('GITEA_MAX_PAGES', 100),
];
