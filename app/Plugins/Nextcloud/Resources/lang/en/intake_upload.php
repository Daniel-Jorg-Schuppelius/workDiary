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
        'server_url' => 'Upload channel: server URL',
        'server_url_help' => 'Nextcloud address (https) for upload links of customer intakes — separate from document intake and backup.',
        'username' => 'Upload channel: user',
        'app_password' => 'Upload channel: app password',
        'app_password_help' => 'Revocable app password from Nextcloud (Settings → Security), never the account password.',
        'base_folder' => 'Upload channel: base folder',
        'link_days' => 'Upload channel: link validity (days)',
        'link_days_help' => '1 to 90 days; afterwards the link is revoked after the last transfer.',
    ],
    'health' => [
        'attention' => 'At least one upload link of customer intakes could not be fetched.',
    ],
];
