<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Cloud-Dokumenteingang (Feature 080).
return [
    'validation' => [
        'pattern_empty' => 'Das Pfadmuster darf nicht leer sein.',
        'pattern_triple_star' => 'Ungültiges Muster: „***" ist nicht erlaubt (nur * und **).',
        'unknown_variable' => 'Unbekannte Pfadvariable :variable.',
        'duplicate_variable' => 'Pfadvariable :variable kommt mehrfach vor.',
    ],
    'title' => [
        'index' => 'Cloud-Dokumenteingang',
        'subtitle' => 'Dokumente aus überwachten Cloud-Ordnern lesend übernehmen und per Ordnerregel in Rechnungseingang und DMS routen.',
        'empty' => 'Noch keine Cloud-Verbindung angebunden.',
    ],
    'field' => [
        'provider' => 'Provider',
        'name' => 'Name',
        'account' => 'Konto',
        'root_folder' => 'Stammordner',
        'routes' => 'Regeln',
        'status' => 'Status',
        'account_unconfirmed' => 'Konto noch nicht bestätigt',
        'container' => 'Container/Drive',
        'root_folder_id' => 'Stammordner-ID (optional)',
    ],
    'action' => [
        'connect_dropbox' => 'Dropbox verbinden',
        'connect_microsoft' => 'Microsoft 365 verbinden',
        'connect_google' => 'Google Drive verbinden',
        'connect_nextcloud' => 'Nextcloud verbinden',
        'preview' => 'Vorschau',
        'save_folder' => 'Ordner übernehmen',
        'disconnect' => 'Trennen',
        'disconnect_confirm' => 'Verbindung wirklich trennen? Bereits importierte Dokumente und Übergabenachweise bleiben erhalten; nur Zugriff und Checkpoint werden entfernt.',
    ],
    'flash' => [
        'not_configured' => 'Der Provider ist nicht konfiguriert (App-Schlüssel fehlen in der Installation).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen.',
        'oauth_failed' => 'Die Anmeldung ist fehlgeschlagen (:class).',
        'account_failed' => 'Das Konto konnte nicht bestätigt werden (:class).',
        'connected' => 'Verbindung hergestellt — Konto bestätigt.',
        'folder_selected' => 'Stammordner übernommen — der nächste Lauf startet mit einem frischen Abgleich.',
        'overlapping_root' => 'Der Stammordner überschneidet sich mit der Verbindung „:name" desselben Kontos.',
        'preview_failed' => 'Vorschau fehlgeschlagen (:class).',
        'preview_result' => 'Vorschau (erste Seite:more): :files Dateien, :size — :matched mit Regel-Treffer, :unmatched ohne Zuordnung.',
        'disconnected' => 'Verbindung getrennt — Nachweise und importierte Dokumente bleiben erhalten.',
        'route_saved' => 'Ordnerregel gespeichert.',
        'route_deleted' => 'Ordnerregel gelöscht.',
    ],
    'dropbox' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Dropbox-Ordnern (Cloud-Dokumenteingang) — mit Ordnerregeln, Übergabenachweis und Inbox für unklare Fälle.',
        'health' => [
            'not_configured' => 'Dropbox-App-Schlüssel nicht konfiguriert.',
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Dropbox-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'ok' => 'Dropbox-Verbindungen in Ordnung.',
            'error' => 'Health-Prüfung fehlgeschlagen (:class).',
        ],
    ],
    'google' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Google-Drive-Ordnern (Cloud-Dokumenteingang) — Meine Ablage und Shared Drives; Rollout bis zur Google-OAuth-Verifikation blockiert.',
        'health' => [
            'not_configured' => 'Google-Drive-Client-Schlüssel nicht konfiguriert.',
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Google-Drive-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'ok' => 'Google-Drive-Verbindungen in Ordnung.',
            'error' => 'Health-Prüfung fehlgeschlagen (:class).',
        ],
    ],
    'nextcloud' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Nextcloud-Ordnern (WebDAV) — mit Ordnerregeln, Übergabenachweis und Inbox für unklare Fälle.',
        'health' => [
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Nextcloud-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'ok' => 'Nextcloud-Verbindungen in Ordnung.',
            'error' => 'Health-Prüfung fehlgeschlagen (:class).',
        ],
        'connect_title' => 'Nextcloud verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden',
        'field' => [
            'server_url' => 'Server-URL',
            'server_url_help' => 'Nur HTTPS. Beispiel: https://cloud.example.com',
            'username' => 'Benutzername',
            'app_password' => 'App-Passwort',
            'app_password_help' => 'Ein widerrufbares App-Passwort (Einstellungen › Sicherheit), nie das reguläre Kontopasswort.',
        ],
        'validation' => [
            'https_required' => 'Die Server-URL muss mit https:// beginnen.',
            'unsafe_url' => 'Die Server-URL muss öffentlich erreichbar sein (kein internes/privates Ziel).',
        ],
    ],
    'route' => [
        'heading' => 'Ordnerregeln',
        'create' => 'Regel anlegen',
        'edit' => 'Regel bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'delete_confirm' => 'Ordnerregel wirklich löschen?',
        'basics' => 'Regel',
        'pattern' => 'Pfadmuster',
        'pattern_help' => '* = ein Ordnersegment, ** = beliebig tief; Variablen: {customer_number}, {project_number}, {order_number}, {asset_number}, {contract_number}. Unsichere Treffer landen in der Integrations-Inbox.',
        'target' => 'Zielbereich',
        'document_type' => 'Dokumenttyp',
        'priority' => 'Priorität',
        'extensions' => 'Erlaubte Endungen',
        'extensions_help' => 'Kommagetrennt; leer = alle (außer global blockierte).',
        'max_size' => 'Max. Größe (Bytes)',
        'auto_version' => 'Neue Revisionen automatisch als Version übernehmen',
        'auto_version_help' => 'Ohne Freigabe werden neue Revisionen als Versionsvorschlag in die Inbox gelegt.',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'empty' => 'Noch keine Regel — ohne gültige Regel importiert die Verbindung nicht.',
    ],
    'log' => [
        'heading' => 'Importprotokoll',
        'empty' => 'Noch keine Übergaben.',
        'path' => 'Quellpfad',
        'revision' => 'Revision',
        'reason' => 'Grund',
        'when' => 'Zeitpunkt',
    ],
];
