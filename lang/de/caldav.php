<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : caldav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CalDAV',
    'intro' => 'WorkDiary-Termine werden in einen externen CalDAV-Kalender (Nextcloud/ownCloud) publiziert — On-Premise, ohne Microsoft-/Google-Konto. WorkDiary bleibt führend; abgesagte Termine verschwinden dort, wiederholte Läufe erzeugen keine Dubletten.',

    'health' => [
        'ok' => 'Verbunden',
        'failing' => 'Nicht erreichbar',
        'inactive' => 'Inaktiv',
    ],

    'action' => [
        'publish' => 'Jetzt publizieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'connection' => [
        'heading' => 'Anbindung',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Termin im CalDAV-Kalender gelöscht',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'base_url' => 'DAV-Basis-URL',
        'base_url_help' => 'Nextcloud: .../remote.php/dav (ohne Kalenderpfad).',
        'username' => 'Benutzername',
        'app_password' => 'App-Passwort',
        'password_keep' => '•••••••• (unverändert lassen)',
        'password_help' => 'Nextcloud: Einstellungen → Sicherheit → App-Passwort. Wird verschlüsselt gespeichert.',
        'calendar_path' => 'Kalenderpfad (Collection)',
        'calendar_path_help' => 'Relativ zur Basis-URL, z. B. calendars/team/dienstplan.',
        'active' => 'Aktiv',
        // MVP-610b: Rückimport ist Opt-in — er ändert Daten.
        'two_way' => 'Zwei-Wege: externe Änderungen als Inbox-Vorschläge importieren',
        'two_way_help' => 'Rückimport der Kalender-Collection über sync-collection (RFC 6578), sonst über ein Zeitfenster mit ETag-Vergleich — neue externe Termine, externe Änderungen an publizierten und Löschungen landen als Fälle in der Integrations-Inbox (nie blinde Anlage).',
        'scopes' => 'Publizierte Inhalte',
        'scope_events' => 'Termine',
        'scope_schedule' => 'Dienstpläne & Urlaube',
        'scopes_help' => 'Welche Inhalte in diese Collection publiziert werden. Ohne Auswahl nur Termine.',
    ],

    'flash' => [
        'saved' => 'CalDAV-Anbindung gespeichert.',
        'publish_done' => 'Publish gestartet.',
        'disconnected' => 'CalDAV-Anbindung getrennt. Bereits publizierte Termine bleiben extern erhalten.',
        'no_connection' => 'Keine aktive CalDAV-Anbindung vorhanden.',
        'invalid_url' => 'Die Basis-URL muss mit http:// oder https:// beginnen.',
        'password_required' => 'Für eine neue Anbindung ist ein App-Passwort erforderlich.',
    ],
];
