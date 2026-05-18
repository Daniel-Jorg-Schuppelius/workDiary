<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : license.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Ed25519 Public Key (base64, 32 Byte raw). Wird vom LicenseService zur
    // Signaturprüfung verwendet. Privater Schlüssel bleibt ausschließlich beim
    // Herausgeber (Schuppelius).
    'public_key' => env('LICENSE_PUBLIC_KEY', ''),

    // Optional: Lizenzschlüssel direkt aus der .env (z. B. für SaaS-Instanzen
    // unter eigener Kontrolle). Hat Vorrang vor der Datei im Storage.
    'key' => env('LICENSE_KEY'),

    // Pfad zur On-Premise-Lizenzdatei (relativ zu storage/app).
    'key_path' => env('LICENSE_KEY_PATH', 'license.key'),

    // Grace-Period in Tagen nach Ablauf, in der die App noch read-only weiter
    // läuft (Warnbanner). 0 = harte Sperre ab Ablaufdatum.
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 14),

    // Lizenzprüfung komplett deaktivieren (z. B. Tests, lokale Entwicklung).
    'enforce' => filter_var(env('LICENSE_ENFORCE', true), FILTER_VALIDATE_BOOL),

    // Cache-TTL für das verifizierte Lizenzobjekt in Sekunden. Bei kurzem TTL
    // wirken Lizenzwechsel schneller, kosten aber Performance.
    'cache_ttl' => (int) env('LICENSE_CACHE_TTL', 300),

    // Routen-Präfixe, die ohne gültige Lizenz erreichbar bleiben müssen
    // (Login, Lizenzeingabe, Health-Check, Assets). Anpassbar via .env.
    'bypass_paths' => [
        'license',
        'license/*',
        'up',
        'login',
        'logout',
    ],

    // Hosts, die ohne Lizenz nutzbar sind (Entwicklung, lokale Tests).
    // Unterstützt exakte Hostnamen sowie '*'-Wildcards (z. B. '*.test').
    // Über LICENSE_DEV_HOSTS in der .env als Komma-Liste überschreibbar.
    'dev_hosts' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('LICENSE_DEV_HOSTS', '127.0.0.1,localhost,::1')
    )))),

    // Environments, in denen die Dev-Host-Liste greift. In allen anderen
    // (insbesondere 'production') wird auch auf 127.0.0.1 die Lizenzprüfung
    // erzwungen, um Umgehungen durch falsch konfigurierte Reverse-Proxies
    // auszuschließen. Über LICENSE_DEV_HOST_ENVS als Komma-Liste anpassbar.
    'dev_host_envs' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('LICENSE_DEV_HOST_ENVS', 'local,testing,development')
    )))),
];
