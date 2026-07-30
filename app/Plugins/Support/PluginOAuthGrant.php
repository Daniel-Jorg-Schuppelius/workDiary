<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginOAuthGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Gemeinsamer OAuth2-Grant-Builder aller Plugin-Verbindungen (Vollreview W3a):
 * baut den Authorization-Code-Grant (+ PKCE) aus der installationsweiten
 * Plugin-Konfiguration (client_id/client_secret/authorize_url/token_url) plus
 * Callback-Route. Die Subklassen liefern nur Config-Quelle, Routen-Namen und
 * ggf. den Scope-Schlüssel. Als Container-Singleton der Austauschpunkt für
 * Tests: dort wird ein Guzzle-`MockHandler`-Client injiziert.
 */
abstract class PluginOAuthGrant {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    /**
     * Installationsweite Plugin-Konfiguration (`<Config>::resolve()`).
     *
     * @return array<string, string|int|bool>
     */
    abstract protected function config(): array;

    /** Routen-Name des OAuth-Callbacks (redirect_uri). */
    abstract protected function callbackRouteName(): string;

    /** Config-Schlüssel der Scopes (Intake/Backup nutzen eigene Schlüssel). */
    protected function scopesKey(): string {
        return 'scopes';
    }

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = $this->config();

        return new OAuth2AuthorizationCodeGrant(
            clientId: (string) $config['client_id'],
            clientSecret: (string) $config['client_secret'],
            authorizeUrl: (string) $config['authorize_url'],
            tokenUrl: (string) $config['token_url'],
            redirectUri: route($this->callbackRouteName()),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', (string) ($this->config()[$this->scopesKey()] ?? ''))));
    }
}
