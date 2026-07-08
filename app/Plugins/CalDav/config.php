<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * CalDAV-Plugin (Feature 058, MVP-126). Die Anbindung (Basis-URL, Zugangsdaten
 * verschlüsselt, Ziel-Collection) liegt PRO ORGANISATION in `caldav_connections`
 * und wird über das Admin-Panel gepflegt. ENV dient nur als globaler
 * Aktivierungs-Fallback für Tests/Konsole.
 *
 * Protokoll: RFC 4791 (CalDAV) über HTTP, Basic-Auth (App-Passwort); ICS per
 * spatie/icalendar-generator (dieselbe Termin→ICS-Abbildung wie die Lese-Feeds).
 */

return [
    'enabled' => env('CALDAV_ENABLED', false),
];
