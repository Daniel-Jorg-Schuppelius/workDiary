<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FedexApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fedex\Api;

use APIToolkit\API\Authentication\OAuth2\{OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant};
use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Shipping\{CarrierConnectionTokenStore, CarrierTokenCache};
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * FedEx-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf dem
 * `php-api-toolkit`-Fundament ({@see PluginApiClient}: Retry/Backoff inkl.
 * `Retry-After`); der Transport kommt aus der {@see PluginHttpFactory} —
 * Tests ersetzen ihn über {@see \Tests\Support\FakePluginHttp}.
 *
 * Auth: OAuth2 Client-Credentials über den Toolkit-Grant
 * ({@see OAuth2ClientCredentialsGrant}, `POST /oauth/token`,
 * client_id/client_secret im Form-Body — Toolkit-Default, FedEx nutzt kein
 * Basic) mit {@see OAuth2ClientCredentialsAuthentication}: Ablauf-Leeway
 * 60 s, ein 401 der Fach-Endpunkte verwirft den Token und wiederholt den
 * Request genau einmal mit frischem Token. Der Access-Token (60 min) liegt
 * über den {@see CarrierConnectionTokenStore} im verschlüsselten
 * {@see CarrierTokenCache} je Organisation/Umgebung. Deckt Ship
 * (`/ship/v1/shipments`, Cancel) und Track (`/track/v1/trackingnumbers`)
 * ab; JSON-Verträge nach der öffentlichen FedEx-Doku — ein Lauf gegen die
 * echte Sandbox (`apis-sandbox.fedex.com`, self-service) steht aus.
 */
class FedexApiClient {
    private PluginApiClient $api;

    private OAuth2ClientCredentialsGrant $grant;

    private CarrierConnectionTokenStore $store;

    private string $base;

    public function __construct(CarrierConnection $connection, CarrierTokenCache $tokens) {
        $cfg = config('plugins.fedex');
        $base = $connection->sandbox
            ? (string) ($cfg['sandbox_base_url'] ?? 'https://apis-sandbox.fedex.com')
            : (string) ($cfg['base_url'] ?? 'https://apis.fedex.com');
        $this->base = rtrim($base, '/');

        $clientId = $connection->credential('client_id') ?? $connection->credential('username');
        $clientSecret = $connection->credential('client_secret') ?? $connection->credential('password');
        if ($clientId === null || $clientSecret === null) {
            throw new RuntimeException('FedEx connection is missing client_id/client_secret.');
        }

        $factory = app(PluginHttpFactory::class);

        // FedEx: Credentials im Form-Body — der Toolkit-Default (AUTH_METHOD_POST).
        $this->grant = $factory->clientCredentialsGrant('fedex', $clientId, $clientSecret, $this->base . '/oauth/token');
        $this->store = new CarrierConnectionTokenStore($tokens, $connection);

        $this->api = $factory->client('fedex', $this->base);
        $this->api->setAuthentication(new OAuth2ClientCredentialsAuthentication($this->grant, $this->store));
    }

    /**
     * Erzeugt eine Sendung inkl. Label (PDF Base64 in
     * `output.transactionShipments[].pieceResponses[].packageDocuments[].encodedLabel`).
     *
     * @param  array<string, mixed>  $body
     */
    public function createShipment(array $body): Response {
        return $this->api->postJson($this->base . '/ship/v1/shipments', $body);
    }

    /**
     * Storniert eine Sendung (PUT /ship/v1/shipments/cancel).
     *
     * @param  array<string, mixed>  $body
     */
    public function cancelShipment(array $body): Response {
        return $this->api->putJson($this->base . '/ship/v1/shipments/cancel', $body);
    }

    /** Ruft den Sendungsverlauf zu einer Trackingnummer ab. */
    public function trackShipment(string $trackingNumber): Response {
        return $this->api->postJson($this->base . '/track/v1/trackingnumbers', [
            'includeDetailedScans' => true,
            'trackingInfo' => [
                ['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]],
            ],
        ]);
    }

    /**
     * Verbindungs-/Health-Check: ein frischer Token-Austausch mit den
     * hinterlegten Zugangsdaten (validiert Client-ID/Secret gegen FedEx).
     */
    public function ping(): bool {
        $this->store->clear();
        $this->store->save($this->grant->fetchToken());

        return true; // fetchToken() wirft bei Ablehnung (typisierte ApiException)
    }
}
