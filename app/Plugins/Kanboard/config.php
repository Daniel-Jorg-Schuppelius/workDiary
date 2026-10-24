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
 * Kanboard-Aufgabenimport (Feature 173, Phase 158). JSON-RPC-API unter <Adresse>/jsonrpc.php, HTTP-Basic mit Benutzer „jsonrpc“ und dem Anwendungstoken oder mit Benutzername und persönlichem Zugriffstoken.
 * Zugang je Organisation in den Plugin-Einstellungen; interne Adressen nur mit
 * dem Opt-in `allow_private_network`. Kein Seitenlimit: `getAllTasks` liefert
 * alle Aufgaben eines Projekts in einer Antwort.
 */
return [
    'enabled' => env('KANBOARD_ENABLED', false),
];
