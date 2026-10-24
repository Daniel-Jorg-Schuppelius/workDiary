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
 * FreeScout-Aufgabenimport (Feature 174, Phase 158). REST-API des Moduls „API & Webhooks“ unter <Adresse>/api, Anmeldung mit dem Header X-FreeScout-API-Key.
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('FREESCOUT_ENABLED', false),

    // Seitengröße und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('FREESCOUT_PAGE_SIZE', 50),
    'max_pages' => (int) env('FREESCOUT_MAX_PAGES', 100),
];
