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

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Shipping\CarrierTokenCache;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * UPS-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf dem
 * `php-api-toolkit`-Fundament ({@see PluginApiClient}: Retry/Backoff inkl.
 * `Retry-After`); der Transport kommt aus der {@see PluginHttpFactory} —
 * Tests ersetzen ihn über {@see \Tests\Support\FakePluginHttp}.
 *
 * Auth: OAuth2 Client-Credentials (`POST /security/v1/oauth/token`, Basic
 * client_id:client_secret). Der Access-Token (~4 h) wird über den
 * {@see CarrierTokenCache} je Organisation/Umgebung gehalten; ein 401 der
 * Fach-Endpunkte verwirft ihn und wiederholt den Request genau einmal mit
 * frischem Token. Deckt Shipping (`/api/shipments/<v>/ship`, Void) und
 * Tracking (`/api/track/v1/details/{nr}`) ab; JSON-Verträge nach der
 * öffentlichen UPS-Doku — ein Lauf gegen die echte API steht aus (Sandbox
 * `wwwcie.ups.com`, self-service Developer-Account).
 */
class UpsApiClient {
    private PluginApiClient $api;

    private string $base;

    private string $version;

    private string $clientId;

    private string $clientSecret;

    public function __construct(private readonly CarrierConnection $connection, private readonly CarrierTokenCache $tokens) {
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
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $this->api = app(PluginHttpFactory::class)->client('ups', $this->base);
    }

    /**
     * Erzeugt eine Sendung inkl. Label (GIF Base64 in
     * `ShipmentResults.PackageResults[].ShippingLabel.GraphicImage`).
     *
     * @param  array<string, mixed>  $body
     */
    public function createShipment(array $body): Response {
        return $this->authed('post', $this->base . '/api/shipments/' . $this->version . '/ship', ['json' => $body]);
    }

    /** Storniert (voided) eine Sendung anhand der Shipment Identification Number. */
    public function voidShipment(string $shipmentIdentificationNumber): Response {
        return $this->authed(
            'delete',
            $this->base . '/api/shipments/' . $this->version . '/void/cancel/' . rawurlencode($shipmentIdentificationNumber),
        );
    }

    /** Ruft den Sendungsverlauf zu einer Trackingnummer ab. */
    public function trackShipment(string $trackingNumber): Response {
        return $this->authed(
            'get',
            $this->base . '/api/track/v1/details/' . rawurlencode($trackingNumber),
            [
                'query' => ['locale' => 'de_DE', 'returnSignature' => 'false'],
                // Pflicht-Header der UPS-Track-API.
                'headers' => ['transId' => (string) Str::uuid(), 'transactionSrc' => 'workDiary'],
            ],
        );
    }

    /**
     * Verbindungs-/Health-Check: ein frischer Token-Austausch mit den
     * hinterlegten Zugangsdaten (validiert Client-ID/Secret gegen UPS).
     */
    public function ping(): bool {
        $this->tokens->forget($this->connection);
        $this->token();

        return true; // fetchToken() wirft bei Ablehnung
    }

    /**
     * OAuth2-Token-Austausch (Client-Credentials, Basic-Auth) — ungecacht.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function fetchToken(): array {
        $client = app(PluginHttpFactory::class)->client('ups', $this->base);
        $client->setAuthentication(new BasicAuthentication($this->clientId, $this->clientSecret));

        $response = $client->requestResponse('post', $this->base . '/security/v1/oauth/token', [
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("UPS token request failed (HTTP {$response->status()}).");
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new RuntimeException('UPS token response contained no access_token.');
        }

        // UPS liefert expires_in als String (z. B. "14399").
        return ['access_token' => $token, 'expires_in' => (int) $response->json('expires_in', 0)];
    }

    /** Gültiger Access-Token aus dem Cache; holt bei Bedarf einen frischen. */
    private function token(): string {
        return $this->tokens->remember($this->connection, fn(): array => $this->fetchToken());
    }

    /**
     * Request mit Bearer-Token; bei 401 wird der gecachte Token verworfen und
     * genau einmal mit frischem Token wiederholt.
     *
     * @param  array<string, mixed>  $options
     */
    private function authed(string $method, string $url, array $options = []): Response {
        $response = $this->send($method, $url, $options, $this->token());

        if ($response->status() === 401) {
            $this->tokens->forget($this->connection);
            $response = $this->send($method, $url, $options, $this->token());
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function send(string $method, string $url, array $options, string $token): Response {
        $options['headers'] = array_merge(
            ['Authorization' => 'Bearer ' . $token],
            is_array($options['headers'] ?? null) ? $options['headers'] : [],
        );

        return $this->api->requestResponse($method, $url, $options);
    }
}
