<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHttpFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsGrant;
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Baut die {@see PluginApiClient}-Instanzen der Plugins. Als Container-
 * Singleton ist die Factory der Austauschpunkt für Tests: dort ersetzt
 * {@see \Tests\Support\FakePluginHttp} den Guzzle-Transport durch einen
 * Mock-Handler (Guzzle-`MockHandler`-Muster statt `Http::fake()`).
 */
class PluginHttpFactory {
    /**
     * `$requestInterval` (Sekunden) drosselt aufeinanderfolgende Requests
     * desselben Clients (Toolkit-Throttle) — für tarifgebundene API-Limits
     * (z. B. easybill 10/min, Billbee 2/s). Der Test-Fake ignoriert das
     * Intervall, damit Tests nicht real schlafen.
     */
    public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0): PluginApiClient {
        $client = new PluginApiClient($pluginId, $baseUrl);
        if ($requestInterval > 0) {
            $client->setRequestInterval($requestInterval);
        }

        return $client;
    }

    /**
     * Baut den Client eines Provider-SDKs (eigene {@see ClientAbstract}-
     * Ableitung, z. B. `Orgamax\API\Client`) mit denselben Transport-Defaults
     * wie {@see PluginApiClient}. `$make` erhält den Guzzle-Transport:
     * produktiv `null` — das Toolkit baut ihn selbst inklusive Redirect-
     * Politik —, im Test den Mock-Handler aus {@see \Tests\Support\FakePluginHttp}.
     *
     * @template TClient of ClientAbstract
     *
     * @param  callable(GuzzleClient|null): TClient  $make
     * @return TClient
     */
    public function sdkClient(string $pluginId, string $baseUrl, callable $make): ClientAbstract {
        return $this->configureSdkClient($make($this->sdkTransport($baseUrl)), $pluginId);
    }

    /** Produktiv kein eigener Transport — der Test-Fake liefert hier den Mock-Handler. */
    protected function sdkTransport(string $baseUrl): ?GuzzleClient {
        return null;
    }

    /**
     * @template TClient of ClientAbstract
     *
     * @param  TClient  $client
     * @return TClient
     */
    protected function configureSdkClient(ClientAbstract $client, string $pluginId): ClientAbstract {
        $client->setUserAgent('workDiary-plugin/' . $pluginId);
        $client->setTimeout(10.0);
        $client->setRequestInterval(0.0);
        $client->setMaxRetries(3);

        return $client;
    }

    /**
     * Baut den OAuth2-Client-Credentials-Grant (php-api-toolkit ≥ v2.3.3)
     * eines Plugins gegen den vollständigen Token-Endpunkt — mit denselben
     * Transport-Defaults wie {@see PluginApiClient}; auch hier ersetzen
     * Tests den Guzzle-Transport über {@see \Tests\Support\FakePluginHttp}.
     */
    public function clientCredentialsGrant(string $pluginId, string $clientId, string $clientSecret, string $tokenUrl): OAuth2ClientCredentialsGrant {
        return $this->configureGrant(new OAuth2ClientCredentialsGrant($clientId, $clientSecret, $tokenUrl), $pluginId);
    }

    /** Transport-Defaults analog {@see PluginApiClient}: User-Agent, Timeout 10 s, kein Throttling. */
    protected function configureGrant(OAuth2ClientCredentialsGrant $grant, string $pluginId): OAuth2ClientCredentialsGrant {
        $grant->setUserAgent('workDiary-plugin/' . $pluginId);
        $grant->setTimeout(10.0);
        $grant->setRequestInterval(0.0);
        $grant->setMaxRetries(3);

        return $grant;
    }
}
