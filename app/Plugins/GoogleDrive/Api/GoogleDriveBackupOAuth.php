<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveBackupOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Api;

use App\Plugins\GoogleDrive\GoogleDriveConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für das Google-Drive-BACKUPZIEL
 * (Feature 017 Phase 32, MVP-363). Getrennt vom Intake-Flow: eigener
 * Redirect, Scope `drive.file` (sieht NUR app-erzeugte Dateien).
 */
class GoogleDriveBackupOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return GoogleDriveConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.backup-targets.google.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'backup_scopes';
    }

    /** Ohne diesen Scope ist das Ziel `blocked` (keine Sonderwege). */
    public function requiredScope(): string {
        return 'https://www.googleapis.com/auth/drive.file';
    }
}
