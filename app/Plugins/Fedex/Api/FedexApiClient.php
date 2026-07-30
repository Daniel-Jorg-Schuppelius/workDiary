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

use App\Services\Shipping\AbstractCarrierOAuthApiClient;
use Illuminate\Http\Client\Response;

/**
 * FedEx-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf der gemeinsamen
 * Client-Credentials-Basis {@see AbstractCarrierOAuthApiClient}
 * (`php-api-toolkit`-Fundament: Retry/Backoff inkl. `Retry-After`, Transport
 * aus der PluginHttpFactory, Token verschlüsselt je Organisation/Umgebung).
 *
 * FedEx-Spezifika: `POST /oauth/token` mit client_id/client_secret im
 * Form-Body (Toolkit-Default, FedEx nutzt kein Basic; Access-Token 60 min).
 * Deckt Ship (`/ship/v1/shipments`, Cancel) und Track
 * (`/track/v1/trackingnumbers`) ab; JSON-Verträge nach der öffentlichen
 * FedEx-Doku — ein Lauf gegen die echte Sandbox (`apis-sandbox.fedex.com`,
 * self-service) steht aus.
 */
class FedexApiClient extends AbstractCarrierOAuthApiClient {
    protected function configKey(): string {
        return 'fedex';
    }

    protected function carrierLabel(): string {
        return 'FedEx';
    }

    protected function tokenEndpointPath(): string {
        return '/oauth/token';
    }

    /** @return array{production: string, sandbox: string} */
    protected function defaultBaseUrls(): array {
        return [
            'production' => 'https://apis.fedex.com',
            'sandbox' => 'https://apis-sandbox.fedex.com',
        ];
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
}
