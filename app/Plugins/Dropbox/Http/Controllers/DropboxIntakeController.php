<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Dropbox\Api\{DropboxClient, DropboxOAuth};
use App\Plugins\Dropbox\DropboxConfig;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeOAuthController};

/**
 * OAuth-Anbindung einer Dropbox-Quelle (Feature 080, MVP-353). Flow in der
 * Basis (C7); `token_access_type=offline` liefert kurzlebiges Access- +
 * Refresh-Token.
 */
class DropboxIntakeController extends IntakeOAuthController {
    protected function provider(): CloudIntakeProvider {
        return CloudIntakeProvider::Dropbox;
    }

    protected function connectionName(): string {
        return 'Dropbox';
    }

    protected function isConfigured(): bool {
        return DropboxConfig::isConfigured();
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(DropboxOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(DropboxOAuth::class)->scopes();
    }

    protected function account(CloudDocumentConnection $connection): IntakeAccount {
        return (new DropboxClient($connection))->account();
    }

    protected function stateCachePrefix(): string {
        return 'cloud-intake-dropbox-oauth-state';
    }

    protected function extraAuthorizeParams(): array {
        return ['token_access_type' => 'offline'];
    }

    /** Re-Auth wird wieder lauffähig; sonst Basis-Regel (Ordner + Route). */
    protected function connectedStatus(CloudDocumentConnection $connection): CloudIntakeConnectionStatus {
        return $connection->isRunnable()
            ? CloudIntakeConnectionStatus::Active
            : parent::connectedStatus($connection);
    }
}
