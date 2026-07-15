<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Backup\BackupProvider;
use App\Plugins\Dropbox\Api\DropboxBackupOAuth;
use App\Plugins\Dropbox\DropboxConfig;
use App\Plugins\Support\Backup\BackupTargetOAuthController;

/**
 * OAuth-Anbindung des Dropbox-BACKUPZIELS (Feature 017 Phase 32, MVP-363);
 * Ablauf im gemeinsamen {@see BackupTargetOAuthController}.
 */
class DropboxBackupTargetController extends BackupTargetOAuthController {
    protected function provider(): BackupProvider {
        return BackupProvider::Dropbox;
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(DropboxBackupOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(DropboxBackupOAuth::class)->scopes();
    }

    protected function requiredScope(): string {
        return app(DropboxBackupOAuth::class)->requiredScope();
    }

    protected function isConfigured(): bool {
        return DropboxConfig::isConfigured();
    }

    /** @return array<string, string> */
    protected function extraAuthorizeParams(): array {
        // token_access_type=offline: kurzlebiges Access- + Refresh-Token.
        return ['token_access_type' => 'offline'];
    }
}
