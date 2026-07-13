<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : google_calendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Google Kalender',
    'intro' => 'WorkDiary-Termine werden über die Google Calendar API in einen Kalender des verbundenen Google-Kontos publiziert. WorkDiary bleibt führend; abgesagte Termine verschwinden dort, wiederholte Läufe erzeugen keine Dubletten. Externe Termine werden nie gelesen.',
    'plugin_description' => 'Publiziert Termine idempotent in einen Google-Kalender (Calendar API v3, OAuth2) — Nur-Publish, Ziel-Kalender wählbar.',
    'not_configured_hint' => 'GOOGLE_CALENDAR_CLIENT_ID/SECRET sind nicht gesetzt — die Verbindung braucht zuerst einen OAuth-Client in der Google Cloud Console (Kalender-Scopes sind „sensitive": Brand-Verification bzw. Consent-Typ „Internal" für Workspace).',

    'health' => [
        'badge_ok' => 'Verbunden',
        'badge_failing' => 'Nicht erreichbar',
        'badge_inactive' => 'Inaktiv',
        'not_configured' => 'Google Kalender ist nicht konfiguriert (GOOGLE_CALENDAR_CLIENT_ID/SECRET fehlen).',
        'no_org_context' => 'Konfiguriert (keine Organisation im Kontext).',
        'no_connection' => 'Keine Google-Kalender-Verbindung hergestellt.',
        'inactive' => 'Google-Kalender-Verbindung ist getrennt oder deaktiviert.',
        'ok' => 'Verbunden — Kalenderliste abrufbar.',
        'failing' => 'Google Calendar API nicht erreichbar oder Zugriff verweigert.',
        'error' => 'Google-Calendar-Fehler (:class).',
    ],

    'action' => [
        'connect' => 'Mit Google verbinden',
        'publish' => 'Jetzt publizieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'calendar' => [
        'heading' => 'Ziel-Kalender',
        'help' => 'In welchen Kalender des verbundenen Kontos publiziert wird. Ohne Auswahl der Hauptkalender (primary).',
        'target' => 'Kalender',
        'default' => 'Hauptkalender (primary)',
    ],

    'flash' => [
        'not_configured' => 'Google Kalender ist nicht konfiguriert (GOOGLE_CALENDAR_CLIENT_ID/SECRET fehlen).',
        'state_invalid' => 'Der OAuth-Vorgang ist abgelaufen oder ungültig. Bitte erneut starten.',
        'oauth_denied' => 'Die Verbindung wurde abgelehnt oder abgebrochen.',
        'oauth_failed' => 'Der Token-Austausch ist fehlgeschlagen (:class).',
        'connected' => 'Google-Konto verbunden.',
        'disconnected' => 'Google-Kalender-Verbindung getrennt. Bereits publizierte Termine bleiben extern erhalten.',
        'no_connection' => 'Keine aktive Google-Kalender-Verbindung vorhanden.',
        'calendar_saved' => 'Ziel-Kalender gespeichert.',
        'calendar_invalid' => 'Der gewählte Kalender wurde nicht gefunden.',
        'publish_done' => 'Publish gestartet.',
    ],
];
