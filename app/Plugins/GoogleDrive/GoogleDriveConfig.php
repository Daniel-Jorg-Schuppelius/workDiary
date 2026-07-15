<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive;

/**
 * Aufgelöste Installations-Konfiguration des Google-Drive-Plugins (MVP-355).
 */
class GoogleDriveConfig {
    /** @return array{enabled: bool, client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string, page_size: int, backup_scopes: string, upload_base: string} */
    public static function resolve(): array {
        return [
            'enabled' => (bool) config('plugins.google-drive.enabled', false),
            'client_id' => (string) config('plugins.google-drive.client_id', ''),
            'client_secret' => (string) config('plugins.google-drive.client_secret', ''),
            'api_base' => rtrim((string) config('plugins.google-drive.api_base', 'https://www.googleapis.com/drive/v3'), '/'),
            'authorize_url' => (string) config('plugins.google-drive.authorize_url', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token_url' => (string) config('plugins.google-drive.token_url', 'https://oauth2.googleapis.com/token'),
            'scopes' => (string) config('plugins.google-drive.scopes', 'https://www.googleapis.com/auth/drive.readonly'),
            'page_size' => (int) config('plugins.google-drive.page_size', 200),
            // Cloud-Backupziel (Feature 017 Phase 32, MVP-363).
            'backup_scopes' => (string) config('plugins.google-drive.backup_scopes', 'https://www.googleapis.com/auth/drive.file'),
            'upload_base' => rtrim((string) config('plugins.google-drive.upload_base', 'https://www.googleapis.com/upload/drive/v3'), '/'),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
