<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Dropbox\DropboxConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * OAuth2 Authorization-Code-Grant (+ PKCE) für Dropbox (Feature 080,
 * MVP-353). `token_access_type=offline` erzwingt kurzlebige Access- plus
 * Refresh-Tokens. Container-Singleton = Austauschpunkt für Tests
 * (Guzzle-MockHandler, Muster GoogleCalendarOAuth).
 */
class DropboxOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = DropboxConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.cloud-intake.dropbox.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', DropboxConfig::resolve()['scopes'])));
    }
}
