<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox;

/**
 * Aufgelöste Installations-Konfiguration des Dropbox-Plugins (MVP-353).
 * Muster GoogleCalendarConfig: config() ist im Plugin-Kontext bereits unter
 * `plugins.dropbox` gemergt.
 */
class DropboxConfig {
    /** @return array{enabled: bool, client_id: string, client_secret: string, api_base: string, content_base: string, authorize_url: string, token_url: string, scopes: string, page_size: int, backup_scopes: string} */
    public static function resolve(): array {
        /** @var array{enabled: bool, client_id: string, client_secret: string, api_base: string, content_base: string, authorize_url: string, token_url: string, scopes: string, page_size: int, backup_scopes: string} $config */
        $config = (array) config('plugins.dropbox', []);

        return $config + [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'api_base' => 'https://api.dropboxapi.com/2',
            'content_base' => 'https://content.dropboxapi.com/2',
            'authorize_url' => 'https://www.dropbox.com/oauth2/authorize',
            'token_url' => 'https://api.dropboxapi.com/oauth2/token',
            'scopes' => 'account_info.read files.metadata.read files.content.read',
            'page_size' => 500,
            // Cloud-Backupziel (Feature 017 Phase 32, MVP-363).
            'backup_scopes' => 'account_info.read files.metadata.read files.content.read files.content.write',
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
