<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\GoogleDrive\Api\{GoogleDriveClient, GoogleDriveOAuth};
use App\Plugins\GoogleDrive\GoogleDriveConfig;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeOAuthController};

/**
 * OAuth-Anbindung einer Google-Drive-Quelle (Feature 080, MVP-355). Flow in
 * der Basis (C7); `access_type=offline` + `prompt=consent` sichern das
 * Refresh-Token.
 */
class GoogleDriveIntakeController extends IntakeOAuthController {
    protected function provider(): CloudIntakeProvider {
        return CloudIntakeProvider::Google;
    }

    protected function connectionName(): string {
        return 'Google Drive';
    }

    protected function isConfigured(): bool {
        return GoogleDriveConfig::isConfigured();
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(GoogleDriveOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(GoogleDriveOAuth::class)->scopes();
    }

    protected function account(CloudDocumentConnection $connection): IntakeAccount {
        return (new GoogleDriveClient($connection))->account();
    }

    protected function stateCachePrefix(): string {
        return 'cloud-intake-google-oauth-state';
    }

    protected function extraAuthorizeParams(): array {
        return ['access_type' => 'offline', 'prompt' => 'consent'];
    }
}
