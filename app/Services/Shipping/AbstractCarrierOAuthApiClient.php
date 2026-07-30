<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractCarrierOAuthApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use APIToolkit\API\Authentication\OAuth2\{OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant};
use App\Models\CarrierConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;

/**
 * Gemeinsame Verdrahtung der OAuth2-Client-Credentials-Carrier-Clients
 * (UPS/FedEx, W3d): Sandbox-/Produktiv-Basis-URL aus der Plugin-Config,
 * Credential-Fallback-Kette (client_id??username / client_secret??password),
 * Toolkit-Grant über die {@see PluginHttpFactory} (Tests ersetzen den
 * Transport über {@see \Tests\Support\FakePluginHttp}) und Token-Ablage im
 * verschlüsselten {@see CarrierTokenCache} je Organisation/Umgebung
 * ({@see CarrierConnectionTokenStore}). Die
 * {@see OAuth2ClientCredentialsAuthentication} verwirft den Token bei einem
 * 401 der Fach-Endpunkte und wiederholt genau einmal mit frischem Token.
 * DHL bleibt außen vor (API-Key statt Client-Credentials-Flow).
 */
abstract class AbstractCarrierOAuthApiClient {
    protected PluginApiClient $api;

    protected OAuth2ClientCredentialsGrant $grant;

    protected CarrierConnectionTokenStore $store;

    protected string $base;

    public function __construct(CarrierConnection $connection, CarrierTokenCache $tokens) {
        $cfg = config('plugins.' . $this->configKey());
        $defaults = $this->defaultBaseUrls();
        $base = $connection->sandbox
            ? (string) ($cfg['sandbox_base_url'] ?? $defaults['sandbox'])
            : (string) ($cfg['base_url'] ?? $defaults['production']);
        $this->base = rtrim($base, '/');

        $clientId = $connection->credential('client_id') ?? $connection->credential('username');
        $clientSecret = $connection->credential('client_secret') ?? $connection->credential('password');
        if ($clientId === null || $clientSecret === null) {
            throw new RuntimeException(sprintf('%s connection is missing client_id/client_secret.', $this->carrierLabel()));
        }

        $factory = app(PluginHttpFactory::class);

        $this->grant = $factory->clientCredentialsGrant($this->configKey(), $clientId, $clientSecret, $this->base . $this->tokenEndpointPath());
        $method = $this->tokenAuthMethod();
        if ($method !== null) {
            $this->grant->setTokenAuthMethod($method);
        }
        $this->store = new CarrierConnectionTokenStore($tokens, $connection);

        $this->api = $factory->client($this->configKey(), $this->base);
        $this->api->setAuthentication(new OAuth2ClientCredentialsAuthentication($this->grant, $this->store));
    }

    /** Carrier-Schlüssel für Config (`plugins.<key>`) und PluginHttpFactory. */
    abstract protected function configKey(): string;

    /** Anzeigename in Fehlermeldungen (z. B. "UPS"). */
    abstract protected function carrierLabel(): string;

    /** Pfad des OAuth2-Token-Endpunkts relativ zur Basis-URL. */
    abstract protected function tokenEndpointPath(): string;

    /**
     * Default-Basis-URLs, wenn die Plugin-Config keine setzt.
     *
     * @return array{production: string, sandbox: string}
     */
    abstract protected function defaultBaseUrls(): array;

    /**
     * Auth-Methode am Token-Endpunkt oder null für den Toolkit-Default
     * (Credentials im Form-Body, AUTH_METHOD_POST).
     */
    protected function tokenAuthMethod(): ?string {
        return null;
    }

    /**
     * Verbindungs-/Health-Check: ein frischer Token-Austausch mit den
     * hinterlegten Zugangsdaten (validiert Client-ID/Secret gegen den Carrier).
     */
    public function ping(): bool {
        $this->store->clear();
        $this->store->save($this->grant->fetchToken());

        return true; // fetchToken() wirft bei Ablehnung (typisierte ApiException)
    }
}
