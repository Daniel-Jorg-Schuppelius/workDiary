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
 * GLPI-Aufgabenimport (Feature 174, Phase 158). REST-API unter <Adresse>/apirest.php: Sitzung über initSession mit Benutzer-Token (Authorization: user_token) und optionalem App-Token.
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('GLPI_ENABLED', false),

    // Seitengröße und Seitenlimit je Zuordnung und Lauf.
    // GLPI begrenzt `range` nicht; der tägliche Volllauf liest jede Entität ganz.
    'page_size' => (int) env('GLPI_PAGE_SIZE', 200),
    'max_pages' => (int) env('GLPI_MAX_PAGES', 100),
];
