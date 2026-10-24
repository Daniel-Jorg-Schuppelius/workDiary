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
 * Plane-Aufgabenimport (Feature 173, Phase 158). REST-API unter <Adresse>/api/v1 (Cloud: https://api.plane.so), Anmeldung mit dem Header X-API-Key, Daten je Workspace.
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('PLANE_ENABLED', false),

    // Seitengröße und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('PLANE_PAGE_SIZE', 50),
    'max_pages' => (int) env('PLANE_MAX_PAGES', 100),
];
