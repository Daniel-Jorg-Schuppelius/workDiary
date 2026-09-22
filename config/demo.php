<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Tarif der befristeten Org-Lizenz, die eine Herausgeber-Instanz (Private
    // Key vorhanden) beim Anlegen einer Demo-Organisation ausstellt (MVP-836).
    // Ohne Private Key gilt die Installationslizenz; fehlt auch die, läuft die
    // Demo produktiv im Tarif Free — die Oberfläche sagt das vor dem Anlegen.
    'license_plan' => env('DEMO_LICENSE_PLAN', 'enterprise'),

    // Laufzeit dieser Demo-Lizenz in Tagen.
    'license_days' => (int) env('DEMO_LICENSE_DAYS', 30),

    // Aufbewahrung von Demo-Organisationen in Tagen ab dem letzten Seed;
    // `demo:prune` deaktiviert und löscht ältere endgültig. Leer = aus.
    'retention_days' => env('DEMO_RETENTION_DAYS') !== null && env('DEMO_RETENTION_DAYS') !== ''
        ? (int) env('DEMO_RETENTION_DAYS')
        : null,
];
