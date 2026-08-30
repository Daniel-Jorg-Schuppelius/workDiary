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
use App\Support\UrlSafety;
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
    public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0, ?bool $allowPrivateNetwork = null): PluginApiClient {
        $this->assertTargetAllowed($pluginId, $baseUrl, $allowPrivateNetwork);

        $client = new PluginApiClient($pluginId, $baseUrl);
        if ($requestInterval > 0) {
            $client->setRequestInterval($requestInterval);
        }

        return $client;
    }

    /**
     * Client für Kern-Services außerhalb des Plugin-Systems (OSV/CSAF-Feeds,
     * Eurostat, Update-Check, Nominatim/OSRM, …) — gleiches Toolkit-Fundament
     * und derselbe Test-Fake wie die Plugins, nur mit neutralem User-Agent
     * `workDiary/<service>` statt `workDiary-plugin/<id>`. Zentralisierung
     * 2026-08 (Phase 65): Laravel-`Http::`-Direktnutzung abbauen.
     */
    public function coreClient(string $serviceId, string $baseUrl, float $requestInterval = 0.0, ?bool $allowPrivateNetwork = null): PluginApiClient {
        $client = $this->client($serviceId, $baseUrl, $requestInterval, $allowPrivateNetwork);
        $client->setUserAgent('workDiary/' . $serviceId);

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
        $this->assertTargetAllowed($pluginId, $baseUrl);

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
        $client->setRequestInterval(0.0);
        // Health-Kontext (W3c): reduziertes Timeout-Budget, max. 1 Retry.
        if (\App\Plugins\PluginHealthService::inHealthCheck()) {
            $client->setTimeout((float) config('plugins.health_timeout_seconds', 10));
            $client->setMaxRetries(1);
        } else {
            $client->setTimeout(10.0);
            $client->setMaxRetries(3);
        }

        return $client;
    }

    /**
     * Baut den OAuth2-Client-Credentials-Grant (php-api-toolkit ≥ v2.3.3)
     * eines Plugins gegen den vollständigen Token-Endpunkt — mit denselben
     * Transport-Defaults wie {@see PluginApiClient}; auch hier ersetzen
     * Tests den Guzzle-Transport über {@see \Tests\Support\FakePluginHttp}.
     */
    public function clientCredentialsGrant(string $pluginId, string $clientId, string $clientSecret, string $tokenUrl): OAuth2ClientCredentialsGrant {
        $this->assertTargetAllowed($pluginId, $tokenUrl);

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

    /**
     * SSRF-Schranke für **jedes** über diese Factory gebaute Ziel
     * (Sicherheitsscan 2026-08-23, S-10).
     *
     * Die Basis-URLs von Kimai, OpenProject, Clockify, Toggl, SevDesk,
     * Easybill, Lexoffice, RemoteSupport und CTI sind org-seitig frei
     * setzbar. Ein Health-Check auf `http://127.0.0.1:<port>` war damit ein
     * Portscan-Orakel, und beim Lexoffice-Dateidownload wurde der
     * Antwort-Body unverändert an den Browser durchgereicht — also keine
     * blinde SSRF, sondern eine mit Antwort. Der Juli-Fix hatte nur
     * WebDAV/CalDAV/Zammad/JTL/CardDAV einzeln nachgerüstet; hier sitzt die
     * Prüfung an der **einen** Stelle, durch die alle gehen.
     *
     * **Selbst gehostete Ziele sind erlaubt — aber ausdrücklich.** Wer eine
     * eigene Kimai-, OpenProject- oder Ollama-Instanz im Netz betreibt,
     * setzt am Plugin `allow_private_network`. Das ist dasselbe auditierte
     * Opt-in wie bei JTL/CardDAV.
     */
    protected function assertTargetAllowed(string $pluginId, string $baseUrl, ?bool $allowPrivateNetwork = null): void {
        if (trim($baseUrl) === '') {
            return;
        }

        UrlSafety::assertAcceptableExternalBaseUrl(
            $baseUrl,
            $allowPrivateNetwork ?? $this->allowsPrivateNetwork($pluginId),
            'Plugin ' . $pluginId,
            'Ziel-URL',
            'Für selbst gehostete Instanzen die Einstellung „Private Netzwerke erlauben" aktivieren.',
        );
    }

    /**
     * Auditiertes Opt-in für Ziele im privaten Netz.
     *
     * Zwei Quellen: die Plugin-Einstellung `allow_private_network` (Org-Ebene)
     * und die Betreiber-Liste `plugins.private_network_targets` — letztere für
     * Kern-Dienste ohne Plugin-Einstellungen, etwa ein selbst gehostetes
     * Nominatim/OSRM. Rufer mit eigener Kennung (KI-Verbindungen:
     * `is_local`) geben den Wert direkt mit.
     */
    protected function allowsPrivateNetwork(string $pluginId): bool {
        if (in_array($pluginId, (array) config('plugins.private_network_targets', []), true)) {
            return true;
        }

        try {
            return PluginSettingsResolver::for($pluginId)->bool('allow_private_network', false);
        } catch (\Throwable) {
            // Kein Plugin-Kontext (Kern-Services, Installationslauf): dann
            // bleibt es bei der strengen Prüfung.
            return false;
        }
    }

}
