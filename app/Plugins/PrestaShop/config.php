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
 * PrestaShop-Bestellimport (Feature 175, Phase 158). Webservice unter
 * <Shop-Adresse>/api, Anmeldung mit einem Webservice-Schlüssel (Basic-Auth,
 * Schlüssel als Benutzer). Der Schlüssel braucht Leserecht auf orders,
 * customers, currencies und order_states. Adresse, Schlüssel und Zeitzone des
 * Shops stehen je Organisation in den Plugin-Einstellungen; interne Adressen
 * nur mit dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('PRESTASHOP_ENABLED', false),

    // Bestellungen je Abruf und Seitenlimit je Lauf.
    'page_size' => (int) env('PRESTASHOP_PAGE_SIZE', 100),
    'max_pages' => (int) env('PRESTASHOP_MAX_PAGES', 200),
];
