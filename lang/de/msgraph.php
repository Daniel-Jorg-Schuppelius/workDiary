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
    'title' => 'Microsoft 365',
    'calendar_heading' => 'Kalender',
    'intro' => 'WorkDiary-Termine werden über Microsoft Graph in einen Kalender des verbundenen Microsoft-365-Kontos publiziert. WorkDiary bleibt führend; abgesagte Termine verschwinden dort, wiederholte Läufe erzeugen keine Dubletten. Externe Termine werden nie gelesen.',
    'plugin_description' => 'Publiziert Termine idempotent in einen Microsoft-365-Kalender (Microsoft Graph, OAuth2) — Nur-Publish, Ziel-Kalender wählbar.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (und ggf. MSGRAPH_TENANT) sind nicht gesetzt — die Verbindung kann erst nach der App-Registrierung im Microsoft-Tenant hergestellt werden.',

    // Teams-Presence auf der Anwesenheitsseite (Feature 102, F).
    'presence' => [
        'heading' => 'Team (Teams-Status)',
        'state' => [
            'Available' => 'Verfügbar',
            'AvailableIdle' => 'Verfügbar (inaktiv)',
            'Busy' => 'Beschäftigt',
            'BusyIdle' => 'Beschäftigt (inaktiv)',
            'DoNotDisturb' => 'Nicht stören',
            'Away' => 'Abwesend',
            'BeRightBack' => 'Bin gleich zurück',
            'Offline' => 'Offline',
            'PresenceUnknown' => 'Unbekannt',
        ],
    ],
    // Free/Busy im Termin-Dialog (Feature 102, C2).
    'availability' => [
        'check' => 'Verfügbarkeit prüfen (Microsoft 365)',
        'hint' => 'Frei/belegt der gewählten Teilnehmer im Zeitfenster — ohne Termindetails.',
        'missing_input' => 'Bitte Beginn, Ende und mindestens einen Teilnehmer wählen.',
        'no_connection' => 'Keine aktive Microsoft-365-Kalender-Verbindung.',
        'failed' => 'Verfügbarkeitsabfrage fehlgeschlagen.',
        'free' => 'frei',
        'busy' => 'belegt',
        'unknown' => 'unbekannt',
    ],
    // Per-Org-App-Registrierung (Feature 102 Variante B, Plugin-Settings-Dialog).
    'settings' => [
        'client_id' => 'Client-ID (eigene App-Registrierung)',
        'client_id_help' => 'Leer = Instanz-App der Installation. Eigene Entra-App muss dieselben Redirect-URIs registrieren.',
        'client_secret' => 'Client-Secret',
        'client_secret_help' => 'Wird verschlüsselt gespeichert; leer lassen = gespeicherten Wert behalten.',
        'tenant' => 'Tenant (Verzeichnis-ID)',
        'tenant_help' => 'GUID des Entra-Tenants; leer = Wert der Instanz-App (Default „common").',
        'tenant_invalid' => 'Tenant muss eine Verzeichnis-GUID sein (oder common/organizations/consumers).',
    ],
    'health' => [
        'badge_ok' => 'Verbunden',
        'badge_failing' => 'Nicht erreichbar',
        'badge_inactive' => 'Inaktiv',
        'not_configured' => 'Microsoft 365 ist nicht konfiguriert (MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'no_org_context' => 'Konfiguriert (keine Organisation im Kontext).',
        'no_connection' => 'Keine Microsoft-365-Verbindung hergestellt.',
        'inactive' => 'Microsoft-365-Verbindung ist getrennt oder deaktiviert.',
        'side_connections' => 'Microsoft-365-Nebenverbindungen brauchen Aufmerksamkeit (:intake Dokumenteingang, :backup Backup, :mail Mail — erneut anmelden oder Scopes prüfen).',
        'ok' => 'Verbunden — Kalenderliste abrufbar.',
        'failing' => 'Microsoft Graph nicht erreichbar oder Zugriff verweigert.',
        'unreachable' => 'Microsoft Graph momentan nicht erreichbar (Netzwerk-/Timeout-Fehler).',
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
        'teams_meetings' => 'Neue Termine als Teams-Meeting anlegen (Beitrittslink)',
        'teams_meetings_hint' => 'Wirkt nur auf neu publizierte Termine — ein bestehender Termin kann Graph-seitig nicht wieder „offline" gestellt werden.',
        'two_way' => 'Zwei-Wege: externe Änderungen als Inbox-Vorschläge importieren',
        'two_way_hint' => 'Delta-Rückimport des Ziel-Kalenders — neue externe Termine, externe Änderungen an publizierten und Löschungen landen als Fälle in der Integrations-Inbox (nie blinde Anlage).',
    ],

    // Entra-App & tenantweite Freigabe (v2-Admin-Consent).
    'entra' => [
        'heading' => 'Entra-App & tenantweite Freigabe',
        'intro' => 'Benutzer verbinden ihre Microsoft-365-Dienste per Microsoft-Anmeldung (OAuth2, nur delegierte Berechtigungen). Verhindert eine Richtlinie des Microsoft-Tenants die Einwilligung durch Benutzer, kann ein Entra-Administrator die Berechtigungen hier einmalig für die gesamte Organisation freigeben.',
        'consent' => 'Für Organisation freigeben (Admin-Consent)',
        'consent_hint' => 'Öffnet die Microsoft-Anmeldung; erforderlich ist eine Entra-Administratorrolle im Ziel-Tenant. Die Freigabe umfasst Kalender, Mail-Versand, Kontakte, Aufgaben und Dokumenteingang.',
        'redirects' => 'Redirect-URIs für eine eigene App-Registrierung',
        'redirects_hint' => 'Eine kundeneigene Entra-App (Plugin-Einstellungen) muss genau diese URIs als Redirects vom Typ „Web" registrieren:',
        'redirect_calendar' => 'Kalender',
        'redirect_mail' => 'Mail-Versand',
        'redirect_contacts' => 'Kontakte',
        'redirect_tasks' => 'Aufgaben (To Do)',
        'redirect_intake' => 'Dokumenteingang',
        'redirect_adminconsent' => 'Admin-Consent',
        'redirect_backup' => 'Backupziel (nur Instanz-App)',
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
        'admin_consent_granted' => 'Tenantweite Freigabe erteilt — Benutzer können jetzt ohne eigene Einwilligungsabfrage verbinden.',
        'admin_consent_failed' => 'Admin-Consent nicht erteilt (:error).',
    ],
];
