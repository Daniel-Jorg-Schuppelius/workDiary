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
 * Shopware-6-Bestellimport (Feature 175, Phase 158). Admin-API unter
 * <Shop-Adresse>/api, Anmeldung über eine Integration (Zugangs-ID und
 * Sicherheitsschlüssel, OAuth2 Client Credentials). Adresse und Schlüssel
 * stehen je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('SHOPWARE_ENABLED', false),

    // Seitengröße der Suche und Seitenlimit je Lauf.
    'page_size' => (int) env('SHOPWARE_PAGE_SIZE', 100),
    'max_pages' => (int) env('SHOPWARE_MAX_PAGES', 200),
];
