<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpsApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Ups\Api;

use APIToolkit\API\Authentication\OAuth2\{OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant};
use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Shipping\{CarrierConnectionTokenStore, CarrierTokenCache};
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * UPS-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf dem
 * `php-api-toolkit`-Fundament ({@see PluginApiClient}: Retry/Backoff inkl.
 * `Retry-After`); der Transport kommt aus der {@see PluginHttpFactory} —
 * Tests ersetzen ihn über {@see \Tests\Support\FakePluginHttp}.
 *
 * Auth: OAuth2 Client-Credentials über den Toolkit-Grant
 * ({@see OAuth2ClientCredentialsGrant}, `POST /security/v1/oauth/token`,
 * Basic client_id:client_secret) mit
 * {@see OAuth2ClientCredentialsAuthentication}: Ablauf-Leeway 60 s, ein 401
 * der Fach-Endpunkte verwirft den Token und wiederholt den Request genau
 * einmal mit frischem Token. Der Access-Token (~4 h) liegt über den
 * {@see CarrierConnectionTokenStore} im verschlüsselten
 * {@see CarrierTokenCache} je Organisation/Umgebung. Deckt Shipping
 * (`/api/shipments/<v>/ship`, Void) und Tracking
 * (`/api/track/v1/details/{nr}`) ab; JSON-Verträge nach der öffentlichen
 * UPS-Doku — ein Lauf gegen die echte API steht aus (Sandbox
 * `wwwcie.ups.com`, self-service Developer-Account).
 */
class UpsApiClient {
    private PluginApiClient $api;

    private OAuth2ClientCredentialsGrant $grant;

    private CarrierConnectionTokenStore $store;

    private string $base;

    private string $version;

    public function __construct(CarrierConnection $connection, CarrierTokenCache $tokens) {
        $cfg = config('plugins.ups');
        $base = $connection->sandbox
            ? (string) ($cfg['sandbox_base_url'] ?? 'https://wwwcie.ups.com')
            : (string) ($cfg['base_url'] ?? 'https://onlinetools.ups.com');
        $this->base = rtrim($base, '/');
        $this->version = (string) ($cfg['version'] ?? 'v2409');

        $clientId = $connection->credential('client_id') ?? $connection->credential('username');
        $clientSecret = $connection->credential('client_secret') ?? $connection->credential('password');
        if ($clientId === null || $clientSecret === null) {
            throw new RuntimeException('UPS connection is missing client_id/client_secret.');
        }

        $factory = app(PluginHttpFactory::class);

        $this->grant = $factory->clientCredentialsGrant('ups', $clientId, $clientSecret, $this->base . '/security/v1/oauth/token');
        $this->grant->setTokenAuthMethod(OAuth2ClientCredentialsGrant::AUTH_METHOD_BASIC); // UPS: Basic am Token-Endpunkt
        $this->store = new CarrierConnectionTokenStore($tokens, $connection);

        $this->api = $factory->client('ups', $this->base);
        $this->api->setAuthentication(new OAuth2ClientCredentialsAuthentication($this->grant, $this->store));
    }

    /**
     * Erzeugt eine Sendung inkl. Label (GIF Base64 in
     * `ShipmentResults.PackageResults[].ShippingLabel.GraphicImage`).
     *
     * @param  array<string, mixed>  $body
     */
    public function createShipment(array $body): Response {
        return $this->api->postJson($this->base . '/api/shipments/' . $this->version . '/ship', $body);
    }

    /** Storniert (voided) eine Sendung anhand der Shipment Identification Number. */
    public function voidShipment(string $shipmentIdentificationNumber): Response {
        return $this->api->deleteResponse(
            $this->base . '/api/shipments/' . $this->version . '/void/cancel/' . rawurlencode($shipmentIdentificationNumber),
        );
    }

    /** Ruft den Sendungsverlauf zu einer Trackingnummer ab. */
    public function trackShipment(string $trackingNumber): Response {
        return $this->api->getResponse(
            $this->base . '/api/track/v1/details/' . rawurlencode($trackingNumber),
            ['locale' => 'de_DE', 'returnSignature' => 'false'],
            [
                // Pflicht-Header der UPS-Track-API (App-Spezifikum, bleibt hier).
                'headers' => ['transId' => (string) Str::uuid(), 'transactionSrc' => 'workDiary'],
            ],
        );
    }

    /**
     * Verbindungs-/Health-Check: ein frischer Token-Austausch mit den
     * hinterlegten Zugangsdaten (validiert Client-ID/Secret gegen UPS).
     */
    public function ping(): bool {
        $this->store->clear();
        $this->store->save($this->grant->fetchToken());

        return true; // fetchToken() wirft bei Ablehnung (typisierte ApiException)
    }
}
