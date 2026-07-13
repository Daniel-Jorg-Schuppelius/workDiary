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

    /*
    | CSP Stufe 2: Alpine läuft im @alpinejs/csp-Build (kein eval/new Function)
    | → 'unsafe-eval' entfällt aus script-src. DASSELBE Flag steuert den
    | Vite-Build-Switch (vite.config.js, Alias alpinejs → @alpinejs/csp):
    | nach dem Umschalten zwingend `npm run build` ausführen, sonst passt der
    | CSP-Header nicht zum ausgelieferten Bundle. Erst nach Browser-Smoke-Test
    | aller interaktiven Seiten (Dialoge, Tabs, Picker, Gantt, Stoppuhr)
    | aktivieren — der CSP-Build ändert Laufzeit-Semantik.
    */
    'csp_alpine_csp_build' => env('ALPINE_CSP_BUILD', false),
];
