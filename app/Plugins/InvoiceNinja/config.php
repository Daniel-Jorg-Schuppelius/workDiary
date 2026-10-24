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
 * Invoice-Ninja-Belegabruf (Feature 176, Phase 158). REST-API v5 unter
 * <Adresse>/api/v1, Anmeldung mit einem API-Token (Header X-API-TOKEN).
 * Adresse, Token und der Schalter „Belege abrufen“ stehen je Organisation in
 * den Plugin-Einstellungen; interne Adressen nur mit `allow_private_network`.
 */
return [
    'enabled' => env('INVOICENINJA_ENABLED', false),

    // Belege je Seite des Abrufs.
    'page_size' => (int) env('INVOICENINJA_PAGE_SIZE', 100),
];
