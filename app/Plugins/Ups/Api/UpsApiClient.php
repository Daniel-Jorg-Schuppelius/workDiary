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

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsGrant;
use App\Models\CarrierConnection;
use App\Services\Shipping\{AbstractCarrierOAuthApiClient, CarrierTokenCache};
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * UPS-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf der gemeinsamen
 * Client-Credentials-Basis {@see AbstractCarrierOAuthApiClient}
 * (`php-api-toolkit`-Fundament: Retry/Backoff inkl. `Retry-After`, Transport
 * aus der PluginHttpFactory, Token verschlüsselt je Organisation/Umgebung).
 *
 * UPS-Spezifika: `POST /security/v1/oauth/token` mit Basic
 * client_id:client_secret (Access-Token ~4 h), API-Version im Ship-Pfad.
 * Deckt Shipping (`/api/shipments/<v>/ship`, Void) und Tracking
 * (`/api/track/v1/details/{nr}`) ab; JSON-Verträge nach der öffentlichen
 * UPS-Doku — ein Lauf gegen die echte API steht aus (Sandbox
 * `wwwcie.ups.com`, self-service Developer-Account).
 */
class UpsApiClient extends AbstractCarrierOAuthApiClient {
    private string $version;

    public function __construct(CarrierConnection $connection, CarrierTokenCache $tokens) {
        parent::__construct($connection, $tokens);
        $this->version = (string) (config('plugins.ups.version') ?? 'v2409');
    }

    protected function configKey(): string {
        return 'ups';
    }

    protected function carrierLabel(): string {
        return 'UPS';
    }

    protected function tokenEndpointPath(): string {
        return '/security/v1/oauth/token';
    }

    protected function tokenAuthMethod(): ?string {
        return OAuth2ClientCredentialsGrant::AUTH_METHOD_BASIC; // UPS: Basic am Token-Endpunkt
    }

    /** @return array{production: string, sandbox: string} */
    protected function defaultBaseUrls(): array {
        return [
            'production' => 'https://onlinetools.ups.com',
            'sandbox' => 'https://wwwcie.ups.com',
        ];
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
}
