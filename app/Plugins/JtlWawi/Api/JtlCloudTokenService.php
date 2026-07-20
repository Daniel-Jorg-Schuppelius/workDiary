<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlCloudTokenService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use APIToolkit\API\Authentication\OAuth2\{OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant};
use App\Models\JtlConnection;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\Support\PluginHttpFactory;

/**
 * OAuth2-Client-Credentials-Austausch für das JTL-Cloud-Gateway
 * (Feature 078, MVP-317): `POST /oauth2/token` mit Basic Auth aus
 * clientId/clientSecret → JWT (~24 h). Seit Vollaudit 2026-07 (N32) läuft
 * der Austausch über den Toolkit-Grant
 * ({@see PluginHttpFactory::clientCredentialsGrant} + AUTH_METHOD_BASIC,
 * {@see OAuth2ClientCredentialsAuthentication}); die Persistenz (Token +
 * Ablauf, verschlüsselt an der Verbindung) übernimmt der
 * {@see JtlConnectionTokenStore}. Das frühere 2-Minuten-Sicherheitsfenster
 * von hasValidCloudToken() bleibt als expiryLeeway=120 erhalten (Toolkit-
 * Default wäre 60 s).
 */
class JtlCloudTokenService {
    /**
     * Sicherheitsfenster vor Token-Ablauf in Sekunden — entspricht dem
     * bisherigen hasValidCloudToken()-Fenster (2 Minuten).
     */
    private const EXPIRY_LEEWAY_SECONDS = 120;

    public function __construct(private readonly PluginHttpFactory $http) {}

    /**
     * Liefert einen gültigen Bearer-Token; erneuert bei Bedarf über den
     * Toolkit-Grant und persistiert Token + Ablauf an der Verbindung.
     *
     * @throws JtlApiException wenn der Token-Endpunkt ablehnt (Verbindung erneuern)
     */
    public function ensureToken(JtlConnection $connection): string {
        $store = new JtlConnectionTokenStore($connection);

        $grant = $this->http->clientCredentialsGrant(
            JtlWawiPlugin::ID,
            (string) $connection->client_id,
            (string) $connection->client_secret,
            (string) config('plugins.' . JtlWawiPlugin::ID . '.cloud_token_url'),
        );
        $grant->setTokenAuthMethod(OAuth2ClientCredentialsGrant::AUTH_METHOD_BASIC);

        $auth = new OAuth2ClientCredentialsAuthentication($grant, $store, expiryLeeway: self::EXPIRY_LEEWAY_SECONDS);

        try {
            // Löst bei Bedarf den Token-Austausch aus und persistiert via Store.
            $auth->getAuthHeaders();
        } catch (\Throwable $e) {
            throw new JtlApiException(
                'JTL-Cloud: Token-Austausch fehlgeschlagen — Verbindung erneuern. (' . $e->getMessage() . ')',
                502,
            );
        }

        $token = $store->load()?->getAccessToken() ?? '';
        if ($token === '') {
            throw new JtlApiException('JTL-Cloud: Token-Antwort ohne access_token — Verbindung erneuern.', 502);
        }

        return $token;
    }
}
