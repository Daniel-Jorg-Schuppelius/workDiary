<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Msgraph\MsgraphConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * OAuth2 Authorization-Code-Grant (+ PKCE) für den LESENDEN
 * Cloud-Dokumenteingang über Microsoft Graph (Feature 080, MVP-354).
 * Nutzt dieselbe App-Registrierung wie die Kalender-Verbindung
 * (Feature 058), aber eigene Intake-Scopes und einen eigenen Callback —
 * das Aktivieren eines Imports erteilt nie Kalender-/Schreibrechte.
 */
class MsgraphIntakeOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = MsgraphConfig::resolve();

        // authorize_url/token_url kommen aus MsgraphConfig bereits mit
        // eingesetztem Tenant.
        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.cloud-intake.microsoft.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', (string) MsgraphConfig::resolve()['intake_scopes'])));
    }
}
