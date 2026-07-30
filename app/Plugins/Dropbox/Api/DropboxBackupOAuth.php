<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxBackupOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Api;

use App\Plugins\Dropbox\DropboxConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für das Dropbox-BACKUPZIEL
 * (Feature 017 Phase 32, MVP-363). Bewusst getrennt vom Intake-Flow
 * ({@see DropboxOAuth}): eigener Redirect, eigene (Schreib-)Scopes,
 * systemweite Verbindung.
 */
class DropboxBackupOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return DropboxConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.backup-targets.dropbox.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'backup_scopes';
    }

    /** Ohne diesen Scope ist das Ziel `blocked` (keine Sonderwege). */
    public function requiredScope(): string {
        return 'files.content.write';
    }
}
