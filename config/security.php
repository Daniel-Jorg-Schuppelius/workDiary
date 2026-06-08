<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    | Wenn aktiv, ersetzt die CSP 'unsafe-inline' in script-src durch ein
    | Pro-Request-Nonce. Alle Inline-Scripts tragen via @cspNonce das Nonce.
    | Erst nach einem Browser-Smoke-Test (alle Seiten ohne CSP-Konsolenfehler)
    | produktiv aktivieren — sonst werden nicht-noncte Inline-Scripts blockiert.
    */
    'csp_script_nonce' => env('CSP_SCRIPT_NONCE', false),
];
