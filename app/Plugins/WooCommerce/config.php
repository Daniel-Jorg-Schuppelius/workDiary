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
 * WooCommerce-Bestellimport (Feature 175, Phase 158). REST-API unter
 * <Shop-Adresse>/wp-json/wc/v3, Anmeldung mit Consumer Key und Secret
 * (Leserecht) über HTTPS. Adresse und Schlüssel stehen je Organisation in den
 * Plugin-Einstellungen; interne Adressen nur mit dem Opt-in
 * `allow_private_network`.
 */
return [
    'enabled' => env('WOOCOMMERCE_ENABLED', false),

    // Seitengröße (WooCommerce-Höchstwert 100) und Seitenlimit je Lauf.
    'page_size' => (int) env('WOOCOMMERCE_PAGE_SIZE', 100),
    'max_pages' => (int) env('WOOCOMMERCE_MAX_PAGES', 200),
];
