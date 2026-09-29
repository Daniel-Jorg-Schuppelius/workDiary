<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Telefonauskunft: löst unbekannte Rufnummern über selbst gestellte HTTP-
 | Auskunftsdienste in Namen auf und speist sie als Vorschlag/Anreicherung in
 | Anruf-Import, CTI und Stammdaten. Eingehängt vom PhoneDirectoryServiceProvider
 | unter `plugins.phonedirectory`. ENV nur als Fallback (Tests/Konsole).
 */
return [
    'enabled' => env('PHONE_DIRECTORY_ENABLED', false),
    // Eine Auskunft je Zeile: URL (E.164 via {number}-Platzhalter oder als Query number=), optional "|Token".
    'endpoints' => env('PHONE_DIRECTORY_ENDPOINTS', ''),
];
