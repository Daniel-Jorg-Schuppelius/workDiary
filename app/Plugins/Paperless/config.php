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
 * Paperless-ngx als Quelle des Cloud-Dokumenteingangs (Feature 170, Phase 158).
 * REST-API unter <Basis-URL>/api, Anmeldung mit dem API-Token eines Paperless-
 * Benutzers (`Authorization: Token …`). Basis-URL und Token stehen je
 * Organisation in den Plugin-Einstellungen; interne Adressen nur mit dem
 * Opt-in `allow_private_network`.
 */
return [
    'enabled' => env('PAPERLESS_ENABLED', false),

    // Dokumente je Seite des Eingangslaufs (je Dokument ein Metadatenabruf).
    'page_size' => (int) env('PAPERLESS_PAGE_SIZE', 25),
    // Seitenlimit der ID-Liste je Zyklus (250 IDs je Seite).
    'max_id_pages' => (int) env('PAPERLESS_MAX_ID_PAGES', 400),
];
