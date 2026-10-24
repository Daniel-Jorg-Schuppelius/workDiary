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
 * Akaunting-Belegabruf (Feature 176, Phase 158). REST-API unter <Adresse>/api,
 * Anmeldung per Basic-Auth mit E-Mail und Passwort eines Benutzers mit
 * API-Recht, die Firma über den Header X-Company. Zugang und der Schalter
 * „Belege abrufen“ stehen je Organisation in den Plugin-Einstellungen;
 * interne Adressen nur mit `allow_private_network`.
 */
return [
    'enabled' => env('AKAUNTING_ENABLED', false),

    // Belege je Seite des Abrufs.
    'page_size' => (int) env('AKAUNTING_PAGE_SIZE', 100),
];
