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
 * Leantime-Aufgabenimport (Feature 173, Phase 158). JSON-RPC-API unter <Adresse>/api/jsonrpc, Anmeldung mit dem Header x-api-key (API-Schlüssel aus den Firmeneinstellungen).
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`. Kein Seitenlimit: `Tickets.getAll`
 * liefert alle Tickets eines Projekts in einer Antwort.
 */
return [
    'enabled' => env('LEANTIME_ENABLED', false),
];
