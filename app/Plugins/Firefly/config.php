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
 * Firefly III (Feature 176, Phase 158): Kontobewegungen als Abgleichquelle,
 * nur lesend. REST-API unter <Adresse>/api/v1, Anmeldung mit einem Personal
 * Access Token. Adresse und Token stehen je Organisation in den
 * Plugin-Einstellungen; interne Adressen nur mit `allow_private_network`.
 */
return [
    'enabled' => env('FIREFLY_ENABLED', false),

    // Bewegungen je Seite und Seitenlimit je Lauf.
    'page_size' => (int) env('FIREFLY_PAGE_SIZE', 100),
    'max_pages' => (int) env('FIREFLY_MAX_PAGES', 100),
];
