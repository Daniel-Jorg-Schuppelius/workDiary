<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_nextcloud' => 'Nextcloud verbinden',
    ],
    'field' => [
        'name' => 'Name',
    ],
    'nextcloud' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Nextcloud-Ordnern (WebDAV) — mit Ordnerregeln, Übergabenachweis und Inbox für unklare Fälle.',
        'health' => [
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Nextcloud-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'backup_attention' => 'Das Nextcloud-Backupziel braucht Aufmerksamkeit (Re-Auth/blockiert) — betrifft alle Organisationen.',
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
];
