<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DhlApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dhl\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * DHL-API-Client (Feature 059, MVP-128) auf dem `php-api-toolkit`-Fundament:
 * Basic-Auth mit dem GK-Zugang plus dem Gateway-Header `dhl-api-key`, Retry/
 * Backoff inkl. `Retry-After` aus dem Toolkit. Der Transport kommt aus der
 * {@see PluginHttpFactory} — Tests ersetzen ihn über
 * {@see \Tests\Support\FakePluginHttp} (Guzzle-MockHandler).
 *
 * Deckt die DHL Parcel DE Shipping API v2 (Label/Storno) und die „Shipment
 * Tracking – Unified" API (Sendungsverfolgung) ab. Die JSON-Verträge folgen der
 * öffentlichen DHL-Doku; ein Lauf gegen die echte API setzt GK-Zugangsdaten und
 * einen freigeschalteten `dhl-api-key` voraus.
 */
class DhlApiClient {
    private PluginApiClient $api;

    private string $base;

    public function __construct(CarrierConnection $connection) {
        $cfg = config('plugins.dhl');
        $base = $connection->sandbox
            ? (string) ($cfg['sandbox_base_url'] ?? 'https://api-sandbox.dhl.com')
            : (string) ($cfg['base_url'] ?? 'https://api-eu.dhl.com');
        $this->base = rtrim($base, '/');

        $user = $connection->credential('username');
        $password = $connection->credential('password');
        $apiKey = $connection->credential('api_key');
        if ($user === null || $password === null || $apiKey === null) {
            throw new RuntimeException('DHL connection is missing username/password/api_key.');
        }

        $this->api = app(PluginHttpFactory::class)->client('dhl', $this->base);
        $this->api->setAuthentication(new BasicAuthentication($user, $password));
        $this->api->addDefaultHeader('dhl-api-key', $apiKey);
    }

    /**
     * Erzeugt eine Sendung inkl. Label (`includeDocs=include` liefert das
     * PDF Base64-kodiert in `items[].label.b64`).
     *
     * @param  array<string, mixed>  $body
     */
    public function createOrder(array $body): Response {
        return $this->api->postJson(
            $this->base . '/parcel/de/shipping/v2/orders?includeDocs=include',
            $body,
        );
    }

    /** Storniert eine noch nicht produzierte Sendung anhand ihrer Sendungsnummer. */
    public function deleteOrder(string $shipmentNo): Response {
        return $this->api->deleteResponse(
            $this->base . '/parcel/de/shipping/v2/orders',
            ['query' => ['shipment' => $shipmentNo]],
        );
    }

    /** Ruft den Sendungsverlauf zu einer Trackingnummer ab. */
    public function trackShipment(string $trackingNumber): Response {
        return $this->api->getResponse(
            $this->base . '/track/shipments',
            ['trackingNumber' => $trackingNumber, 'service' => 'parcel-de'],
        );
    }

    /**
     * Verbindungs-/Health-Check: eine Tracking-Abfrage mit den hinterlegten
     * Zugangsdaten. Alles außer 401/403 (und Verbindungsfehlern) gilt als
     * „erreichbar & autorisiert" — eine unbekannte Nummer liefert 404/200.
     */
    public function ping(): bool {
        $response = $this->trackShipment('00000000000000000000');

        return $response->status() !== 401 && $response->status() !== 403;
    }
}
