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

    /*
    | Angriffserkennung (Feature 096, MVP-445): Schwellwert-Regeln über die
    | persistierten security_events. Scope 'global' zählt alle Ereignisse im
    | Fenster, 'ip' die auffälligste Einzel-IP. Alarme feuern nur beim
    | Zustandswechsel (security:evaluate, 5-min-Takt).
    */
    'events' => [
        'retention_days' => (int) env('SECURITY_EVENTS_RETENTION_DAYS', 90),
        'thresholds' => [
            ['key' => 'auth_failed_global', 'event' => 'auth.failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 50],
            ['key' => 'auth_failed_ip', 'event' => 'auth.failed', 'scope' => 'ip', 'window_minutes' => 10, 'limit' => 20],
            ['key' => 'two_factor_failed_global', 'event' => 'auth.2fa_failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 10],
            ['key' => 'api_token_invalid_global', 'event' => 'api.token_invalid', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 30],
            ['key' => 'webhook_signature_global', 'event' => 'webhook.signature_invalid', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 20],
            // Massenangriff (Feature 097, MVP-449): deutlich über der normalen
            // Alarmschwelle; `crisis => true` eröffnet einen CrisisAlert
            // (Quittierungspflicht) statt einer normalen Notification.
            ['key' => 'auth_failed_mass', 'event' => 'auth.failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 300, 'crisis' => true],
        ],
    ],

    /*
    | Impossible-Travel-Erkennung (Feature 097, MVP-449). Wirkt nur mit
    | lokaler `.mmdb` (config/geoip.php) — ohne sie ruht die Prüfung still.
    | Mindestdistanz unterdrückt Pendel-/Mobilfunk-Rauschen, die
    | Geschwindigkeitsschwelle liegt auf Linienflug-Niveau.
    */
    'impossible_travel' => [
        'enabled' => (bool) env('SECURITY_IMPOSSIBLE_TRAVEL', true),
        'min_distance_km' => (float) env('SECURITY_IMPOSSIBLE_TRAVEL_MIN_KM', 300),
        'max_speed_kmh' => (float) env('SECURITY_IMPOSSIBLE_TRAVEL_MAX_KMH', 900),
    ],

    /*
    | Optionale IP-Allowlist für den Plattform-Adminbereich (Feature 096,
    | MVP-446): Komma-Liste von IPs/CIDRs. Leer = aus. Wirkt NUR auf
    | Plattform-Admins im admin.*-Bereich — Org-Admins bleiben unberührt
    | (Aussperr-Risiko begrenzen).
    */
    'platform_admin_ip_allowlist' => env('PLATFORM_ADMIN_IP_ALLOWLIST', ''),
];
