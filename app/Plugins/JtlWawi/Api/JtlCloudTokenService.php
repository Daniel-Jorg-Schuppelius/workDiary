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

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Models\JtlConnection;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\Support\PluginHttpFactory;

/**
 * OAuth2-Client-Credentials-Austausch für das JTL-Cloud-Gateway
 * (Feature 078, MVP-317): `POST /oauth2/token` mit Basic Auth aus
 * clientId/clientSecret → JWT (~24 h). Token + Ablauf werden verschlüsselt
 * an der Verbindung gehalten und mit Sicherheitsfenster erneuert.
 *
 * Hinweis Toolkit-first (korrigiert, Vollaudit 2026-07, N32): das
 * `php-api-toolkit` BIETET inzwischen einen Client-Credentials-Grant
 * (`PluginHttpFactory::clientCredentialsGrant` + AUTH_METHOD_BASIC,
 * OAuth2ClientCredentialsAuthentication) — dieser Handaustausch ist also
 * KEINE Vorlage für neue Plugins. Die Migration (inkl. TokenStore-Adapter
 * nach dem Muster CarrierConnectionTokenStore und Abgleich Sicherheits-
 * fenster vs. Toolkit-Leeway 60 s) steht bewusst zurück, bis der
 * JTL-Wawi-Pilot mit echten Credentials läuft — mit dem Nutzer abstimmen.
 */
class JtlCloudTokenService {
    public function __construct(private readonly PluginHttpFactory $http) {}

    /**
     * Liefert einen gültigen Bearer-Token; erneuert bei Bedarf.
     *
     * @throws JtlApiException wenn der Token-Endpunkt ablehnt (Verbindung erneuern)
     */
    public function ensureToken(JtlConnection $connection): string {
        if ($connection->hasValidCloudToken()) {
            return (string) $connection->access_token;
        }

        $tokenUrl = (string) config('plugins.' . JtlWawiPlugin::ID . '.cloud_token_url');
        $client = $this->http->client(JtlWawiPlugin::ID, $tokenUrl);
        $client->setAuthentication(new BasicAuthentication(
            (string) $connection->client_id,
            (string) $connection->client_secret,
        ));

        $response = $client->requestResponse('post', '', [
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        if (! $response->successful()) {
            throw new JtlApiException(
                sprintf('JTL-Cloud: Token-Austausch fehlgeschlagen (HTTP %d) — Verbindung erneuern.', $response->status()),
                $response->status(),
            );
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($token === '') {
            throw new JtlApiException('JTL-Cloud: Token-Antwort ohne access_token — Verbindung erneuern.', 502);
        }

        $connection->forceFill([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn)),
        ])->save();

        return $token;
    }
}
