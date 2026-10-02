<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : domain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'error' => 'Healthcheck fehlgeschlagen (:class).',
        'no_org_context' => 'Kein Organisationskontext.',
        'ok' => 'DomainReselling-Verbindungen in Ordnung.',
        'pilot_open' => 'Verbindung aktiv, realer Pilot noch offen.',
    ],
    'plugin' => [
        'description' => 'Domains eines DomainReselling-Kontos mit Kunden verbinden und kontrolliert verwalten.',
    ],
    'settings' => [
        'connection_note' => 'Zugangsdaten (Login/Passwort) werden je Verbindung verwaltet — nicht hier.',
        'connection_note_link' => 'Zu den DomainReselling-Verbindungen',
        'timeout' => 'API-Timeout (Sekunden)',
        'timeout_help' => 'Maximale Wartezeit je Provider-Request. Standard: 20.',
        'check_budget_per_hour' => 'Prüfbudget je Stunde',
        'check_budget_per_hour_help' => 'Maximale Verfügbarkeitsprüfungen je Organisation und Stunde — schützt vor Strafpunkten des Providers. Standard: 300.',
        'check_cache_ttl' => 'Prüf-Cache (Sekunden)',
        'check_cache_ttl_help' => 'So lange wird ein Verfügbarkeitsergebnis zwischengespeichert; Cache-Treffer verbrauchen kein Budget. Standard: 300.',
        'list_page_size' => 'Listen-Seitengröße',
        'list_page_size_help' => 'Batchgröße der paginierten Domain-Listenabfragen beim Abgleich. Standard: 100.',
        'stale_after_hours' => 'Veraltet nach (Stunden)',
        'stale_after_hours_help' => 'Datenalter, ab dem eine Domain-Projektion als veraltet markiert wird. Standard: 24.',
        'range_error' => 'Bitte eine ganze Zahl zwischen :min und :max angeben.',
    ],
];
