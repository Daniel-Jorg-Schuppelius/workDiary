<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\GoogleCalendar\GoogleCalendarConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Baut den OAuth2-Authorization-Code-Grant (+ PKCE) für Google
 * (MVP-328, Bauturbo A8) aus der installationsweiten Konfiguration.
 * Offline-Access (`access_type=offline` + `prompt=consent`) stellt das
 * Refresh-Token sicher. Als Container-Singleton der Austauschpunkt für
 * Tests: dort wird ein Guzzle-`MockHandler`-Client injiziert (Todoist-Muster).
 */
class GoogleCalendarOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = GoogleCalendarConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.google-calendar.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', GoogleCalendarConfig::resolve()['scopes'])));
    }
}
