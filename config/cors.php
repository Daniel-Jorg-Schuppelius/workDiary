<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Cross-Origin-Freigaben.
 *
 * Ohne diese Datei galt die Framework-Vorgabe `allowed_origins => ['*']` für
 * die gesamte REST-Schnittstelle (Sicherheitsaudit 2026-09-13): Jede beliebige
 * Seite durfte im Browser Antworten der API lesen. Ausnutzbar war das nur mit
 * einem gültigen Token — aber eine offene Freigabe ist keine Absicht, sondern
 * eine ausgelassene Entscheidung.
 *
 * Vorgabe jetzt: nur die eigene Anwendungsadresse. Zusätzliche Herkünfte
 * (eingebettete Portale, eigene Apps im Browser) über CORS_ALLOWED_ORIGINS,
 * kommagetrennt. Server-zu-Server-Aufrufe und mobile Anwendungen senden keinen
 * Origin und sind davon nicht betroffen.
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('APP_URL', ''))),
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Keine Cookies über fremde Herkünfte: die API arbeitet mit Token.
    'supports_credentials' => false,
];
