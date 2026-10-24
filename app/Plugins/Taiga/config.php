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
 * Taiga-Aufgabenimport (Feature 173, Phase 158). REST-API unter <Adresse>/api/v1 (Cloud: https://api.taiga.io); je Lauf Anmeldung mit Benutzername und Passwort, das Sitzungstoken läuft ab.
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('TAIGA_ENABLED', false),

    // Seitengröße und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('TAIGA_PAGE_SIZE', 50),
    'max_pages' => (int) env('TAIGA_MAX_PAGES', 100),
];
