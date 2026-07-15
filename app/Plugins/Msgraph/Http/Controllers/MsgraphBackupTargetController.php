<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Backup\BackupProvider;
use App\Plugins\Msgraph\Api\MsgraphBackupOAuth;
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Backup\BackupTargetOAuthController;

/**
 * OAuth-Anbindung des Microsoft-Graph-BACKUPZIELS (Feature 017 Phase 32,
 * MVP-363); Ablauf im gemeinsamen {@see BackupTargetOAuthController}.
 */
class MsgraphBackupTargetController extends BackupTargetOAuthController {
    protected function provider(): BackupProvider {
        return BackupProvider::Microsoft;
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(MsgraphBackupOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(MsgraphBackupOAuth::class)->scopes();
    }

    protected function requiredScope(): string {
        return app(MsgraphBackupOAuth::class)->requiredScope();
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }
}
