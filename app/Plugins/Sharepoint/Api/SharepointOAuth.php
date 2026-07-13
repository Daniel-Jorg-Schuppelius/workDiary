<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Sharepoint\SharepointConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Baut den OAuth2-Authorization-Code-Grant (+ PKCE) gegen die Microsoft
 * Identity Platform für die SharePoint-Ablage (MVP-330, Bauturbo A10) aus der
 * installationsweiten Konfiguration (Fallback auf die MSGRAPH_*-Werte,
 * Tenant-Default 'common'). Als Container-Singleton der Austauschpunkt für
 * Tests: dort wird ein Guzzle-`MockHandler`-Client injiziert (A8-Muster).
 */
class SharepointOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = SharepointConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.sharepoint.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', SharepointConfig::resolve()['scopes'])));
    }
}
