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

use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Shipping\CarrierTokenCache;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * FedEx-API-Client (Feature 059, MVP-128 / Bauturbo A5) auf dem
 * `php-api-toolkit`-Fundament ({@see PluginApiClient}: Retry/Backoff inkl.
 * `Retry-After`); der Transport kommt aus der {@see PluginHttpFactory} —
 * Tests ersetzen ihn über {@see \Tests\Support\FakePluginHttp}.
 *
 * Auth: OAuth2 Client-Credentials (`POST /oauth/token`, client_id/client_secret
 * im Form-Body — FedEx nutzt kein Basic). Der Access-Token (60 min) wird über
 * den {@see CarrierTokenCache} je Organisation/Umgebung gehalten; ein 401 der
 * Fach-Endpunkte verwirft ihn und wiederholt den Request genau einmal mit
 * frischem Token. Deckt Ship (`/ship/v1/shipments`, Cancel) und Track
 * (`/track/v1/trackingnumbers`) ab; JSON-Verträge nach der öffentlichen
 * FedEx-Doku — ein Lauf gegen die echte Sandbox (`apis-sandbox.fedex.com`,
 * self-service) steht aus.
 */
class FedexApiClient {
    private PluginApiClient $api;

    private string $base;

    private string $clientId;

    private string $clientSecret;

    public function __construct(private readonly CarrierConnection $connection, private readonly CarrierTokenCache $tokens) {
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
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $this->api = app(PluginHttpFactory::class)->client('fedex', $this->base);
    }

    /**
     * Erzeugt eine Sendung inkl. Label (PDF Base64 in
     * `output.transactionShipments[].pieceResponses[].packageDocuments[].encodedLabel`).
     *
     * @param  array<string, mixed>  $body
     */
    public function createShipment(array $body): Response {
        return $this->authed('post', $this->base . '/ship/v1/shipments', ['json' => $body]);
    }

    /**
     * Storniert eine Sendung (PUT /ship/v1/shipments/cancel).
     *
     * @param  array<string, mixed>  $body
     */
    public function cancelShipment(array $body): Response {
        return $this->authed('put', $this->base . '/ship/v1/shipments/cancel', ['json' => $body]);
    }

    /** Ruft den Sendungsverlauf zu einer Trackingnummer ab. */
    public function trackShipment(string $trackingNumber): Response {
        return $this->authed('post', $this->base . '/track/v1/trackingnumbers', [
            'json' => [
                'includeDetailedScans' => true,
                'trackingInfo' => [
                    ['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]],
                ],
            ],
        ]);
    }

    /**
     * Verbindungs-/Health-Check: ein frischer Token-Austausch mit den
     * hinterlegten Zugangsdaten (validiert Client-ID/Secret gegen FedEx).
     */
    public function ping(): bool {
        $this->tokens->forget($this->connection);
        $this->token();

        return true; // fetchToken() wirft bei Ablehnung
    }

    /**
     * OAuth2-Token-Austausch (Client-Credentials im Form-Body) — ungecacht.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function fetchToken(): array {
        $client = app(PluginHttpFactory::class)->client('fedex', $this->base);

        $response = $client->requestResponse('post', $this->base . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("FedEx token request failed (HTTP {$response->status()}).");
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new RuntimeException('FedEx token response contained no access_token.');
        }

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
