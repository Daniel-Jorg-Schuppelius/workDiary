<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Vikunja-Aufgabenimport (Feature 173, Phase 158). REST-API unter <Adresse>/api/v1, Anmeldung mit einem API-Token (Bearer).
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('VIKUNJA_ENABLED', false),

    // Seitengröße und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('VIKUNJA_PAGE_SIZE', 50),
    'max_pages' => (int) env('VIKUNJA_MAX_PAGES', 100),
];
