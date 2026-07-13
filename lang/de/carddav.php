<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carddav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CardDAV',
    'intro' => 'Kontakte aus einem self-hosted CardDAV-Adressbuch (Nextcloud/Radicale/Baïkal) werden gelesen und als Zuordnungsvorschläge in die Integrations-Inbox eingespeist — kein automatisches Zusammenführen, kein Schreiben auf Kundendaten. Unveränderte Karten werden übersprungen (UID+ETag).',
    'description' => 'Liest Kontakte aus einem CardDAV-Adressbuch (RFC 6352) und speist sie als Zuordnungsvorschläge in die Integrations-Inbox ein — rein lesend, On-Premise, ohne Microsoft-/Google-Konto.',

    'health' => [
        'ok' => 'Verbunden',
        'failing' => 'Nicht erreichbar',
        'inactive' => 'Inaktiv',
        'no_connection' => 'Keine CardDAV-Anbindung hinterlegt.',
        'inactive_or_incomplete' => 'CardDAV-Anbindung ist deaktiviert oder unvollständig.',
        'unreachable' => 'CardDAV-Server nicht erreichbar oder Zugangsdaten ungültig.',
        'error' => 'CardDAV-Fehler (:class).',
        'last_error' => 'Letzter Fehler: :error',
    ],

    'action' => [
        'discover' => 'Adressbücher suchen',
        'choose_addressbook' => 'Adressbuch übernehmen',
        'sync' => 'Jetzt synchronisieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'connection' => [
        'heading' => 'Anbindung',
    ],

    'addressbook' => [
        'heading' => 'Adressbuch',
        'current' => 'Aktuelle Sync-Quelle: :name',
        'hint' => 'Über „Adressbücher suchen" den Server abfragen und anschließend ein Adressbuch als Sync-Quelle wählen.',
    ],

    'status' => [
        'last_synced' => 'Zuletzt synchronisiert :at.',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'base_url' => 'DAV-Basis-URL',
        'base_url_help' => 'Nextcloud: .../remote.php/dav — Radicale/Baïkal: Server-Wurzel. Die Adressbuch-Suche folgt RFC 6764 (.well-known/carddav).',
        'username' => 'Benutzername',
        'app_password' => 'App-Passwort',
        'password_keep' => '•••••••• (unverändert lassen)',
        'password_help' => 'Bei aktivierter 2FA (z. B. Nextcloud) ist ein App-Passwort Pflicht. Wird verschlüsselt gespeichert.',
        'allow_private_network' => 'Private/interne Adressen erlauben',
        'allow_private_network_help' => 'Nur aktivieren, wenn der CardDAV-Server im eigenen Netz steht (z. B. 192.168.x.x). Wird auditiert.',
        'active' => 'Aktiv',
    ],

    'flash' => [
        'saved' => 'CardDAV-Anbindung gespeichert.',
        'invalid_url' => 'Die Basis-URL muss mit http:// oder https:// beginnen.',
        'private_url_blocked' => 'Die Basis-URL zeigt auf eine private/interne Adresse. Für einen Server im eigenen Netz die Freigabe privater Adressen aktivieren.',
        'password_required' => 'Für eine neue Anbindung ist ein App-Passwort erforderlich.',
        'no_connection' => 'Keine aktive CardDAV-Anbindung vorhanden.',
        'discovery_failed' => 'Adressbuch-Suche fehlgeschlagen — Server nicht erreichbar oder Zugangsdaten ungültig.',
        'no_addressbooks' => 'Auf dem Server wurden keine Adressbücher gefunden.',
        'discovered' => ':count Adressbücher gefunden — bitte eine Sync-Quelle wählen.',
        'addressbook_not_discovered' => 'Bitte zuerst „Adressbücher suchen" ausführen und ein gefundenes Adressbuch wählen.',
        'addressbook_saved' => 'Adressbuch als Sync-Quelle übernommen.',
        'not_syncable' => 'Sync nicht möglich — Anbindung inaktiv, gestört oder kein Adressbuch gewählt.',
        'sync_done' => 'Sync gestartet. Neue Kontakte erscheinen als Vorschläge in der Zuordnungs-Inbox.',
        'disconnected' => 'CardDAV-Anbindung getrennt. Bereits eingespeiste Vorschläge bleiben erhalten.',
    ],
];
