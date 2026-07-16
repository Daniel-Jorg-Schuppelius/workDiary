<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : geoip.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Lokale IP-Geolokalisierung (Feature 085)
    |--------------------------------------------------------------------------
    |
    | Grobe Standortanzeige (Land/Stadt) in der Sitzungsübersicht, aufgelöst
    | über CommonToolkit\Helper\Geo\IpLocationHelper gegen eine LOKALE
    | `.mmdb`-Datenbank (MaxMind GeoLite2 oder DB-IP Lite) — kein externer
    | Netzwerk-Call.
    |
    | Ist `database` leer oder die Datei nicht vorhanden, degradiert die
    | Anzeige sauber: es wird nur die IP gezeigt. Die `.mmdb`-Datei wird vom
    | Betreiber bereitgestellt und aktuell gehalten (z. B. monatlicher
    | `geoipupdate`/Download-Job) — sie gehört NICHT ins Repository.
    |
    */

    'database' => env('GEOIP_DATABASE'),

    'locale' => env('GEOIP_LOCALE', 'de'),
];
