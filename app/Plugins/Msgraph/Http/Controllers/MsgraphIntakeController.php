<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Msgraph\Api\{MsgraphIntakeClient, MsgraphIntakeOAuth};
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeOAuthController};

/**
 * OAuth-Anbindung einer OneDrive-/SharePoint-Quelle (Feature 080, MVP-354):
 * eigener LESENDER Intake-Flow, getrennt von der Kalender-Verbindung
 * (Feature 058). Flow in der Basis (C7).
 */
class MsgraphIntakeController extends IntakeOAuthController {
    protected function provider(): CloudIntakeProvider {
        return CloudIntakeProvider::Microsoft;
    }

    protected function connectionName(): string {
        return 'Microsoft 365';
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function grant(): OAuth2AuthorizationCodeGrant {
        return app(MsgraphIntakeOAuth::class)->grant();
    }

    protected function scopes(): array {
        return app(MsgraphIntakeOAuth::class)->scopes();
    }

    protected function account(CloudDocumentConnection $connection): IntakeAccount {
        return (new MsgraphIntakeClient($connection))->account();
    }

    protected function stateCachePrefix(): string {
        return 'cloud-intake-msgraph-oauth-state';
    }
}
