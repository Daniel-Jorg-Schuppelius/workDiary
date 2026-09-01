<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Cloud-Backupziele',
    'description' => 'Verschlüsselte Offsite-Kopien der gesamten Installation (3-2-1-Strategie). Der Klartext verlässt die Installation nie — hochgeladen werden ausschließlich verschlüsselte Teile.',

    'master_key_missing' => 'BACKUP_MASTER_KEY ist nicht gesetzt — ohne Installations-Backup-Schlüssel können keine Backups erstellt oder wiederhergestellt werden.',
    'recovery_key_missing' => 'Kein Recovery-Schlüssel hinterlegt: Geht der BACKUP_MASTER_KEY verloren, sind alle Cloud-Backups unwiederbringlich verloren. BACKUP_RECOVERY_PUBLIC_KEY setzen und den privaten Schlüssel offline sichern.',

    'connect' => 'Verbinden',
    'reconnect' => 'Neu anmelden',
    'disconnect' => 'Trennen',
    'disconnect_confirm' => 'Verbindung wirklich trennen? Remote-Daten bleiben unberührt; laufende Backups stoppen.',
    'cleanup' => 'Bereinigung',
    'no_connections' => 'Noch kein Backupziel verbunden.',
    'account' => 'Konto',
    'quota' => 'Speicher',
    'quota_value' => ':used von :total belegt',
    'quota_unknown' => 'Speicherstand unbekannt',
    'pilot_note' => 'Pilot offen: Dieser Adapter wurde noch nicht gegen den echten Provider getestet.',

    'nextcloud' => [
        'connect_title' => 'Nextcloud verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden',
        'field' => [
            'name' => 'Name',
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
    // Generisches WebDAV-Backupziel (Feature 123, MVP-612).
    's3' => [
        'connect_title' => 'S3-Backupziel verbinden',
        'connect_legend' => 'S3-kompatibler Objektspeicher',
        'connect_submit' => 'Verbinden und prüfen',
        'selftest_hint' => 'Vor dem Speichern wird eine Testdatei geschrieben, gelesen und wieder gelöscht. Schlägt das fehl, wird das Ziel nicht aktiviert.',
        'field' => [
            'name' => 'Bezeichnung',
            'endpoint' => 'Endpoint (leer für AWS S3)',
            'endpoint_help' => 'Für MinIO, Wasabi, Hetzner oder Scaleway die HTTPS-Adresse eintragen. Bleibt das Feld leer, wird AWS S3 aus der Region abgeleitet.',
            'region' => 'Region',
            'region_help' => 'Bei selbstbetriebenen Speichern oft beliebig — us-east-1 ist der übliche Vorgabewert.',
            'bucket' => 'Bucket',
            'access_key' => 'Access Key',
            'secret_key' => 'Secret Key',
            'secret_key_help' => 'Wird verschlüsselt gespeichert und nie wieder angezeigt.',
            'prefix' => 'Präfix (optional)',
            'prefix_help' => 'Unterordner im Bucket. Darunter legt WorkDiary einen eigenen Pseudonym-Ordner an.',
            'path_style' => 'Bucket im Pfad adressieren (Path-Style)',
            'path_style_help' => 'Für MinIO und die meisten selbstbetriebenen Speicher nötig. AWS S3 braucht es nicht.',
        ],
        'validation' => [
            'https_required' => 'Der Endpoint muss mit https:// beginnen.',
            'unsafe_url' => 'Dieser Endpoint zeigt in ein internes Netz. Freigabe über S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'Das Ziel hat den Schreib-/Lesetest nicht bestanden (:class). Es wurde nicht aktiviert.',
        ],
    ],

    'webdav' => [
        'connect_title' => 'WebDAV-Ziel verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden und testen',
        'selftest_hint' => 'Beim Verbinden wird ein Testordner angelegt, eine Datei geschrieben, zurückgelesen und wieder gelöscht.',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Collection-URL',
            'server_url_help' => 'Nur HTTPS. Die vollständige WebDAV-Collection, z. B. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'password_help' => 'Vorzugsweise ein eigenes, widerrufbares Zugangs-Token statt des Kontopassworts.',
            'base_path' => 'Unterordner (optional)',
            'base_path_help' => 'Leer = direkt in der Collection. Der Pseudonym-Ordner wird darunter angelegt.',
        ],
        'validation' => [
            'https_required' => 'Die Collection-URL muss mit https:// beginnen.',
            'unsafe_url' => 'Die Collection-URL muss öffentlich erreichbar sein (kein internes/privates Ziel).',
        ],
        'flash' => [
            'selftest_failed' => 'Der Verbindungstest ist fehlgeschlagen (:class). Das Ziel wurde nicht aktiviert.',
        ],
    ],
    'generations' => [
        'title' => 'Backup-Generationen',
        'empty' => 'Noch keine Backup-Generation vorhanden.',
        'snapshot' => 'Snapshot',
        'target' => 'Ziel',
        'class' => 'Klasse',
        'age' => 'Erstellt',
        'size' => 'Größe',
        'status' => 'Status',
        'verified' => 'Verifiziert',
        'restore_tested' => 'Restore-Test',
        'restore_pending' => 'gesichert, Wiederherstellung nicht bestätigt',
        'hold' => 'Legal Hold',
        'actions' => 'Aktionen',
        'hold_set_action' => 'Hold setzen',
        'hold_release_action' => 'Hold lösen',
        'delete_action' => 'Löschen',
        'delete_confirm' => 'Generation wirklich löschen? Remote-Daten und Nachweis werden entfernt.',
    ],

    'cleanup_page' => [
        'title' => 'Bereinigung — Remote-Bestand',
        'description' => 'Vorschau der Objekte im Backupbereich dieser Verbindung. Gelöscht wird ausschließlich nach Bestätigung je Generation.',
        'empty' => 'Keine Remote-Objekte im Backupbereich gefunden.',
        'known' => 'Bekannte Generation',
        'orphan' => 'Verwaist (kein Nachweis in der Datenbank)',
        'error' => 'Remote-Bestand konnte nicht geladen werden: :message',
        'back' => 'Zurück zur Übersicht',
    ],

    'flash' => [
        'not_configured' => 'Der Provider ist nicht konfiguriert (Client-ID/-Secret fehlen).',
        'state_invalid' => 'Der Anmeldevorgang ist abgelaufen oder ungültig — bitte erneut starten.',
        'oauth_denied' => 'Die Freigabe wurde abgebrochen oder verweigert.',
        'oauth_failed' => 'Token-Tausch fehlgeschlagen (:class).',
        'account_failed' => 'Kontobestätigung fehlgeschlagen (:class).',
        'scope_missing' => 'Erforderliche Berechtigung fehlt (:scope) — das Ziel ist blockiert.',
        'connected' => 'Backupziel verbunden und aktiv.',
        'disconnected' => 'Verbindung getrennt. Remote-Daten bleiben unberührt.',
        'hold_set' => 'Legal Hold gesetzt — die Generation ist vor Löschung geschützt.',
        'hold_released' => 'Legal Hold gelöst.',
        'hold_blocks_delete' => 'Diese Generation trägt einen Legal Hold und kann nicht gelöscht werden.',
        'cleanup_failed' => 'Remote-Bereinigung fehlgeschlagen (:class).',
        'generation_deleted' => 'Generation entfernt (remote und Nachweis).',
    ],
];
