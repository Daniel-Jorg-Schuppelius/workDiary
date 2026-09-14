<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : learning.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'scorm' => [
        /*
         * Eigener Host für SCORM-Kursinhalte (Subdomain oder eigene Domain).
         *
         * Ein SCORM-Paket ist fremder, ausführbarer Code. Läuft er im Ursprung der
         * Anwendung, darf er deren Schnittstellen im Namen des Lernenden aufrufen.
         * Mit eigenem Host bettet die Player-Seite nur noch eine Hülle von dort ein;
         * die Laufzeit-Aufrufe gehen per postMessage zurück.
         *
         * Leer: SCORM läuft wie bisher im selben Ursprung, der Systemcheck weist
         * darauf hin. Voraussetzung: DNS, TLS und ein vHost-Alias auf dasselbe Docroot.
         */
        'content_url' => env('LEARNING_SCORM_CONTENT_URL'),

        /* Gültigkeit des signierten Pfad-Tokens in Sekunden (Vorgabe: 8 Stunden). */
        'token_ttl' => (int) env('LEARNING_SCORM_TOKEN_TTL', 28800),
    ],
    'cmi5' => [
        /* So lange nimmt das LRS nach dem Start Statements einer Sitzung an (Vorgabe: 8 Stunden). */
        'session_ttl' => (int) env('LEARNING_CMI5_SESSION_TTL', 28800),
    ],
];
