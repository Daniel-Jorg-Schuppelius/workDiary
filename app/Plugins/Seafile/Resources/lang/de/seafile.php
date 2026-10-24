<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : seafile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Dateien aus einer Bibliothek einer selbst gehosteten Seafile-Instanz in den Cloud-Dokumenteingang, nur lesend, und dient als verschlüsseltes Backupziel. Die Regeln des Eingangs leiten Dateien in den Rechnungseingang oder die Dokumentablage.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Seafile-Instanz für den Dokumenteingang, z. B. https://seafile.example.com (ohne /api2).',
        'api_token' => 'API-Token',
        'api_token_help' => 'Konto-Token eines Seafile-Benutzers mit Lesezugriff auf die Bibliothek. WorkDiary ändert für den Eingang in Seafile nichts. Den Token liefert POST /api2/auth-token/ mit Benutzername und Passwort (bei Zwei-Faktor-Anmeldung zusätzlich der Kopf X-Seafile-OTP mit dem aktuellen Code).',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
    'action' => [
        'connect_intake' => 'Seafile verbinden',
    ],
    'connect' => [
        'title' => 'Seafile verbinden',
        'legend' => 'Verbindung',
        'instance' => 'Instanz aus den Plugin-Einstellungen: :instance',
        'name' => 'Name der Verbindung',
        'hint' => 'Danach wählen Sie die Bibliothek, darin den Stammordner, und legen Regeln an. Verschlüsselte Bibliotheken stehen nicht zur Wahl.',
        'submit' => 'Verbindung prüfen',
        'not_configured' => 'Hinterlegen Sie zuerst Adresse und API-Token in den Plugin-Einstellungen.',
        'to_settings' => 'Zu den Plugin-Einstellungen',
    ],
    'container' => [
        'library' => 'Bibliothek',
    ],
    'flash' => [
        'not_configured' => 'Seafile ist nicht eingerichtet: Adresse oder API-Token fehlt in den Plugin-Einstellungen.',
        'instance_changed' => 'Die Plugin-Einstellungen zeigen auf eine andere Seafile-Instanz als diese Verbindung. Trennen Sie die Verbindung zuerst; bereits übernommene Belege bleiben erhalten.',
    ],
    'health' => [
        'not_configured' => 'Seafile ist nicht eingerichtet (Adresse oder Token fehlt).',
        'not_connected' => 'Noch keine Seafile-Verbindung im Cloud-Dokumenteingang.',
        'attention' => 'Die Seafile-Verbindung meldet einen Fehler — siehe Cloud-Dokumenteingang.',
        'backup_attention' => 'Ein Seafile-Backupziel braucht Aufmerksamkeit — siehe Backupziele.',
    ],
    'backup' => [
        'connect_title' => 'Seafile als Backupziel verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden',
        'name' => 'Name',
        'server_url' => 'Server-URL',
        'server_url_help' => 'Nur HTTPS. Beispiel: https://seafile.example.com',
        'api_token' => 'API-Token',
        'api_token_help' => 'Konto-Token eines eigenen Seafile-Benutzers für die Sicherung, mit Schreibzugriff auf die Bibliothek. Den Token liefert POST /api2/auth-token/ mit Benutzername und Passwort (bei Zwei-Faktor-Anmeldung zusätzlich der Kopf X-Seafile-OTP mit dem aktuellen Code).',
        'library' => 'Bibliothek',
        'library_help' => 'Name oder ID einer bestehenden, unverschlüsselten Bibliothek. WorkDiary schreibt nur in einen eigenen Ordner darin.',
        'https_required' => 'Die Server-URL muss mit https:// beginnen.',
        'unsafe_url' => 'Die Server-URL muss öffentlich erreichbar sein (kein internes/privates Ziel).',
        'library_not_found' => 'Diese Bibliothek gibt es für das Konto nicht. Legen Sie sie in Seafile an oder prüfen Sie Name und Freigabe.',
        'library_encrypted' => 'Diese Bibliothek ist verschlüsselt. Wählen Sie eine unverschlüsselte Bibliothek — WorkDiary verschlüsselt die Sicherung selbst.',
        'library_ambiguous' => 'Mehrere Bibliotheken tragen diesen Namen. Geben Sie die ID der Bibliothek an.',
        'library_read_only' => 'Das Konto darf in diese Bibliothek nicht schreiben. Wählen Sie eine Bibliothek mit Lese- und Schreibrecht.',
    ],
];
