<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : intake_upload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Upload-Kanal der Kundeneingänge (MVP-1078): Einstellungen und Health.
 */

return [
    'settings' => [
        'server_url' => 'Upload-Kanal: Server-URL',
        'server_url_help' => 'Nextcloud-Adresse (https) für Upload-Links der Kundeneingänge — getrennt von Dokumenteingang und Backup.',
        'username' => 'Upload-Kanal: Nutzer',
        'app_password' => 'Upload-Kanal: App-Passwort',
        'app_password_help' => 'Widerrufbares App-Passwort aus Nextcloud (Einstellungen → Sicherheit), nie das Kontopasswort.',
        'base_folder' => 'Upload-Kanal: Basisordner',
        'link_days' => 'Upload-Kanal: Gültigkeit der Links (Tage)',
        'link_days_help' => '1 bis 90 Tage; danach wird der Link nach der letzten Übernahme widerrufen.',
    ],
    'health' => [
        'attention' => 'Mindestens ein Upload-Link der Kundeneingänge konnte nicht abgeholt werden.',
    ],
];
