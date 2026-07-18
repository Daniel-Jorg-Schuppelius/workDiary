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
    | Ersetzt die CSP 'unsafe-inline' in script-src durch ein Pro-Request-Nonce.
    | Alle Inline-Scripts tragen via @cspNonce das Nonce. Seit 2026-07-14
    | Default AN (B4/MVP-345); bei CSP-Konsolenfehlern (nicht-nonctes
    | Inline-Script) per CSP_SCRIPT_NONCE=false zurückschalten und die Stelle
    | auf @cspNonce umstellen.
    */
    'csp_script_nonce' => env('CSP_SCRIPT_NONCE', true),

    /*
    | CSP Stufe 2: Alpine läuft im @alpinejs/csp-Build (kein eval/new Function)
    | → 'unsafe-eval' entfällt aus script-src. DASSELBE Flag steuert den
    | Vite-Build-Switch (vite.config.js, Alias alpinejs → @alpinejs/csp):
    | nach dem Umschalten zwingend `npm run build` ausführen, sonst passt der
    | CSP-Header nicht zum ausgelieferten Bundle. Seit 2026-07-14 Default AN
    | (B5/MVP-346; der 3.15.x-CSP-Build wertet Objektliterale, Zuweisungen,
    | Ternaries etc. über einen Sandbox-Parser aus). Bei Laufzeitproblemen
    | ALPINE_CSP_BUILD=false setzen UND npm run build ausführen.
    */
    'csp_alpine_csp_build' => env('ALPINE_CSP_BUILD', true),

    /*
    | CVD-Meldekanal via /.well-known/security.txt (RFC 9116; CRA-Welle 1,
    | WorkDiary-Architecture/security/cra-red-compliance-2026-07.md §5).
    | Ohne konfigurierten Contact liefert der Endpunkt 404 — bewusst kein
    | erfundener Default (analog Rechtstexte-Platzhalter). E-Mail oder URL.
    */
    'txt' => [
        'contact' => env('SECURITY_TXT_CONTACT'),
        'policy' => env('SECURITY_TXT_POLICY'),
        'preferred_languages' => env('SECURITY_TXT_LANGUAGES', 'de, en'),
        // RFC 9116 empfiehlt Expires < 1 Jahr; wird pro Request gerechnet.
        'expires_days' => (int) env('SECURITY_TXT_EXPIRES_DAYS', 180),
    ],
];
