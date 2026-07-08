<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webdav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'WebDAV-Ablage',
    'intro' => 'Freigegebene Dokumente werden nach Dokumenttyp in eine externe WebDAV-Ablage (Nextcloud/ownCloud) gespiegelt — mit Übergabenachweis (Hash, Zeit, Ziel). WorkDiary bleibt führend; externe Änderungen an gespiegelten Dateien werden als Konflikt sichtbar, nie still übernommen.',

    'conflict' => [
        'subtitle' => 'Externe Änderung erkannt — Spiegelung angehalten (kein Überschreiben).',
        'action' => [
            'overwrite' => 'Remote überschreiben',
            'import' => 'Als neue Version importieren',
            'detach' => 'Spiegelung trennen',
        ],
        'confirm' => [
            'overwrite' => 'Die externe Datei mit dem lokalen Stand überschreiben? Die externe Änderung geht verloren.',
            'import' => 'Den externen Stand als neue lokale Version übernehmen?',
            'detach' => 'Die Spiegelung dieses Dokuments dauerhaft trennen? Die Anbindung bleibt aktiv.',
        ],
        'flash' => [
            'overwritten' => 'Externe Datei mit dem lokalen Stand überschrieben.',
            'imported' => 'Externer Stand als neue lokale Version importiert.',
            'detached' => 'Spiegelung dieses Dokuments getrennt.',
            'failed' => 'Konfliktauflösung fehlgeschlagen: :reason',
        ],
        'import_note' => 'Aus WebDAV importiert (Konfliktauflösung).',
    ],

    'health' => [
        'ok' => 'Verbunden',
        'failing' => 'Nicht erreichbar',
        'inactive' => 'Inaktiv',
    ],

    'action' => [
        'mirror' => 'Jetzt spiegeln',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'connection' => [
        'heading' => 'Ablage',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'base_url' => 'Collection-URL',
        'base_url_help' => 'Vollständiger WebDAV-Ordner, z. B. .../remote.php/dav/files/BENUTZER/WorkDiary.',
        'username' => 'Benutzername',
        'app_password' => 'App-Passwort',
        'password_keep' => '•••••••• (unverändert lassen)',
        'password_help' => 'Nextcloud: Einstellungen → Sicherheit → App-Passwort. Wird verschlüsselt gespeichert.',
        'default_folder' => 'Standardordner',
        'active' => 'Aktiv',
        'sources' => 'Gespiegelte Inhalte',
        'source_document' => 'Dokumente (DMS)',
        'source_invoice_pdf' => 'Rechnungen (PDF)',
        'source_protocol_pdf' => 'Protokolle (PDF)',
        'sources_help' => 'Welche Inhalte in diese Ablage gespiegelt werden. Ohne Auswahl nur freigegebene Dokumente.',
    ],

    'folder' => [
        'heading' => 'Dokumenttyp → Ordner',
        'help' => 'Ordnet Dokumenttypen einem Unterordner (relativ zur Collection-URL) zu. Ohne Treffer greift der Standardordner.',
        'type_placeholder' => '— Dokumenttyp —',
        'path_placeholder' => 'Unterordner',
    ],

    'flash' => [
        'saved' => 'WebDAV-Ablage gespeichert.',
        'mirror_done' => 'Spiegellauf gestartet.',
        'disconnected' => 'WebDAV-Ablage getrennt. Bereits gespiegelte Dateien bleiben extern erhalten.',
        'no_connection' => 'Keine aktive WebDAV-Ablage vorhanden.',
        'invalid_url' => 'Die Collection-URL muss mit http:// oder https:// beginnen.',
        'password_required' => 'Für eine neue Ablage ist ein App-Passwort erforderlich.',
    ],
];
