<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphBackupOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\PluginOAuthGrant;

/**
 * OAuth2-Authorization-Code-Grant (+ PKCE) für das Microsoft-Graph-BACKUPZIEL
 * (Feature 017 Phase 32, MVP-363). Getrennt von Kalender- und Intake-Flow:
 * eigener Redirect, eigene Scopes (Files.ReadWrite als engste produktiv
 * verfügbare delegierte Berechtigung — bestätigtes Integrationskonto!).
 */
class MsgraphBackupOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return MsgraphConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.backup-targets.microsoft.oauth.callback';
    }

    protected function scopesKey(): string {
        return 'backup_scopes';
    }

    /** Ohne diesen Scope ist das Ziel `blocked` (keine Sonderwege). */
    public function requiredScope(): string {
        return 'Files.ReadWrite';
    }
}
