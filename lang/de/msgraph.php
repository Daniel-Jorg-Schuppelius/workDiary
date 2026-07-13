<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Microsoft 365-Kalender',
    'intro' => 'WorkDiary-Termine werden über Microsoft Graph in einen Kalender des verbundenen Microsoft-365-Kontos publiziert. WorkDiary bleibt führend; abgesagte Termine verschwinden dort, wiederholte Läufe erzeugen keine Dubletten. Externe Termine werden nie gelesen.',
    'plugin_description' => 'Publiziert Termine idempotent in einen Microsoft-365-Kalender (Microsoft Graph, OAuth2) — Nur-Publish, Ziel-Kalender wählbar.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (und ggf. MSGRAPH_TENANT) sind nicht gesetzt — die Verbindung kann erst nach der App-Registrierung im Microsoft-Tenant hergestellt werden.',

    'health' => [
        'badge_ok' => 'Verbunden',
        'badge_failing' => 'Nicht erreichbar',
        'badge_inactive' => 'Inaktiv',
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'no_org_context' => 'Konfiguriert (keine Organisation im Kontext).',
        'no_connection' => 'Keine Microsoft-365-Verbindung hergestellt.',
        'inactive' => 'Microsoft-365-Verbindung ist getrennt oder deaktiviert.',
        'ok' => 'Verbunden — Kalenderliste abrufbar.',
        'failing' => 'Microsoft Graph nicht erreichbar oder Zugriff verweigert.',
        'error' => 'Microsoft-Graph-Fehler (:class).',
    ],

    'action' => [
        'connect' => 'Mit Microsoft 365 verbinden',
        'publish' => 'Jetzt publizieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'calendar' => [
        'heading' => 'Ziel-Kalender',
        'help' => 'In welchen Kalender des verbundenen Kontos publiziert wird. Ohne Auswahl der Standardkalender.',
        'target' => 'Kalender',
        'default' => 'Standardkalender',
    ],

    'flash' => [
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der OAuth-Vorgang ist abgelaufen oder ungültig. Bitte erneut starten.',
        'oauth_denied' => 'Die Verbindung wurde abgelehnt oder abgebrochen.',
        'oauth_failed' => 'Der Token-Austausch ist fehlgeschlagen (:class).',
        'connected' => 'Microsoft-365-Konto verbunden.',
        'disconnected' => 'Microsoft-365-Verbindung getrennt. Bereits publizierte Termine bleiben extern erhalten.',
        'no_connection' => 'Keine aktive Microsoft-365-Verbindung vorhanden.',
        'calendar_saved' => 'Ziel-Kalender gespeichert.',
        'calendar_invalid' => 'Der gewählte Kalender wurde nicht gefunden.',
        'publish_done' => 'Publish gestartet.',
    ],
];
