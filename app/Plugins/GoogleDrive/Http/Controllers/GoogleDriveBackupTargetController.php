<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Backup\BackupProvider;
use App\Plugins\GoogleDrive\Api\GoogleDriveBackupOAuth;
use App\Plugins\GoogleDrive\GoogleDriveConfig;
use App\Plugins\Support\Backup\BackupTargetOAuthController;

/**
 * OAuth-Anbindung des Google-Drive-BACKUPZIELS (Feature 017 Phase 32,
 * MVP-363); Ablauf im gemeinsamen {@see BackupTargetOAuthController}.
 */
class GoogleDriveBackupTargetController extends BackupTargetOAuthController {
    protected function provider(): BackupProvider {
        return BackupProvider::Google;
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(GoogleDriveBackupOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(GoogleDriveBackupOAuth::class)->scopes();
    }

    protected function requiredScope(): string {
        return app(GoogleDriveBackupOAuth::class)->requiredScope();
    }

    protected function isConfigured(): bool {
        return GoogleDriveConfig::isConfigured();
    }

    /** @return array<string, string> */
    protected function extraAuthorizeParams(): array {
        // access_type=offline + prompt=consent: Google liefert das
        // Refresh-Token nur bei ausdrücklicher Einwilligung erneut.
        return ['access_type' => 'offline', 'prompt' => 'consent'];
    }
}
