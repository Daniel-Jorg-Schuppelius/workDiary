<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : performance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    // Anfragen ab dieser Dauer (ms) landen mit Route, Abfragezahl und Datenbankzeit im Log; 0 schaltet ab.
    'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 1500),

    // Log-Kanal der Meldungen; leer = Standardkanal.
    'log_channel' => env('PERF_LOG_CHANNEL'),

    // Kopfzeile `Server-Timing` für angemeldete Nutzer (Browser-Werkzeuge zeigen Server- und Datenbankzeit).
    'server_timing' => (bool) env('PERF_SERVER_TIMING', false),
];
