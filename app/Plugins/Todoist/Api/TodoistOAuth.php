<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Todoist\TodoistConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Baut den OAuth2-Authorization-Code-Grant für Todoist (Feature 055,
 * MVP-111) aus der installationsweiten Konfiguration. Als Container-Singleton
 * der Austauschpunkt für Tests: dort wird ein Guzzle-`MockHandler`-Client
 * injiziert.
 */
class TodoistOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = TodoistConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.todoist.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    public function scopes(): string {
        return TodoistConfig::resolve()['scopes'];
    }
}
