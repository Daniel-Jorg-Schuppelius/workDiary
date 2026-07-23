<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Calendly\CalendlyConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Baut den OAuth2-Authorization-Code-Grant (+ PKCE) für Calendly (Feature 095)
 * aus der installationsweiten Konfiguration. Die Scopes kommen aus
 * `plugins.calendly.scopes` (ENV) und müssen ggf. `offline_access` enthalten,
 * damit Calendly ein Refresh-Token liefert.
 */
class CalendlyOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = CalendlyConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.calendly.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', CalendlyConfig::resolve()['scopes'])));
    }
}
