<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Dropbox-Plugin (Feature 080, MVP-353): LESENDER Cloud-Dokumenteingang.
 * App-Key/-Secret sind INSTALLATIONS-weit (ENV) — nie je Organisation.
 */

return [
    'enabled' => env('DROPBOX_ENABLED', false),
    'client_id' => env('DROPBOX_APP_KEY', ''),
    'client_secret' => env('DROPBOX_APP_SECRET', ''),
    'api_base' => env('DROPBOX_API_BASE', 'https://api.dropboxapi.com/2'),
    'content_base' => env('DROPBOX_CONTENT_BASE', 'https://content.dropboxapi.com/2'),
    'authorize_url' => env('DROPBOX_AUTHORIZE_URL', 'https://www.dropbox.com/oauth2/authorize'),
    'token_url' => env('DROPBOX_TOKEN_URL', 'https://api.dropboxapi.com/oauth2/token'),
    'scopes' => 'account_info.read files.metadata.read files.content.read',
    // Seitengröße des list_folder-Laufs (Dropbox-Max 2000).
    'page_size' => (int) env('DROPBOX_PAGE_SIZE', 500),
    // Cloud-Backupziel (Feature 017 Phase 32, MVP-363): eigene systemweite
    // Verbindung mit Schreib-Scope, getrennt vom lesenden Dokumenteingang.
    'backup_scopes' => env('DROPBOX_BACKUP_SCOPES', 'account_info.read files.metadata.read files.content.read files.content.write'),
];
