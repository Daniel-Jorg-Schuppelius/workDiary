<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use APIToolkit\Exceptions\ApiException;
use App\Support\UrlSafety;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Gemeinsame HTTP-Basis der Plugins auf dem `php-api-toolkit`-Fundament
 * ({@see ClientAbstract}: Retry/Backoff inkl. `Retry-After`, injizierbares
 * Guzzle, typisierte HTTP-Exceptions). Ersetzt den früheren Laravel-Http-
 * Wrapper `PluginHttp` und behält dessen Vertrag bei:
 *
 * - einheitlicher User-Agent `workDiary-plugin/<id>`, Timeout-Default 10 s,
 *   3 Versuche nur bei transienten Fehlern (Verbindung, 429/503/504);
 * - HTTP-Fehlerstatus werfen nicht, sondern kommen als reguläre
 *   {@see Response} zurück (`throw: false`-Semantik) — die Plugins
 *   entscheiden selbst über `successful()`/Status;
 * - Verbindungsfehler nach ausgeschöpften Versuchen propagieren als
 *   {@see \GuzzleHttp\Exception\ConnectException}.
 *
 * Instanzen entstehen über {@see PluginHttpFactory}, damit Tests den
 * Guzzle-Transport durch einen Mock-Handler ersetzen können.
 */
class PluginApiClient extends ClientAbstract {
    /** Ziele im privaten Netz sind für dieses Plugin ausdrücklich freigegeben (allow_private_network). */
    private bool $privateNetworkAllowed = false;

    /** Nur der eigene Transport baut Verbindungen auf — ein injizierter Client (Tests) wird nicht gebunden. */
    private bool $pinsConnections = false;

    /** @var list<string>|null Bindung des Basis-Hosts; erst beim ersten Abruf ermittelt */
    private ?array $pin = null;

    private bool $pinResolved = false;

    public function __construct(string $pluginId, string $baseUrl, ?GuzzleClient $httpClient = null, bool $allowPrivateNetwork = false) {
        // Vor parent::__construct(): dort entsteht der Guzzle-Client aus buildClientConfig().
        $this->privateNetworkAllowed = $allowPrivateNetwork;
        $this->pinsConnections = $httpClient === null && ! $allowPrivateNetwork;
        parent::__construct($baseUrl, null, false, $httpClient);

        $this->setUserAgent('workDiary-plugin/' . $pluginId);
        $this->setRequestInterval(0.0);
        $this->setDefaultHeaders(['Accept' => 'application/json']);

        // Health-Kontext (Review 2026-08, W3c): ein Check muss nicht dreimal
        // retryen — Budget = plugins.health_timeout_seconds, max. 1 Retry.
        // Greift für Clients, die während des healthCheck() gebaut werden
        // (der übliche Fall: Services werden lazy aufgelöst).
        if (\App\Plugins\PluginHealthService::inHealthCheck()) {
            $this->setTimeout((float) config('plugins.health_timeout_seconds', 10));
            $this->setMaxRetries(1);
        } else {
            $this->setTimeout(10.0);
            $this->setMaxRetries(3);
        }
    }

    /**
     * GET mit Query-Parametern; Fehlerstatus kommt als Response zurück.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options  Guzzle-Optionen (z. B. ['timeout' => 60])
     */
    public function getResponse(string $url, array $query = [], array $options = []): Response {
        if ($query !== []) {
            $options['query'] = $query;
        }

        return $this->send('get', $url, $options);
    }

    /**
     * POST mit JSON-Body; Fehlerstatus kommt als Response zurück.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function postJson(string $url, array $payload = [], array $options = []): Response {
        $options['json'] = $payload;

        return $this->send('post', $url, $options);
    }

    /**
     * PUT mit JSON-Body; Fehlerstatus kommt als Response zurück.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function putJson(string $url, array $payload = [], array $options = []): Response {
        $options['json'] = $payload;

        return $this->send('put', $url, $options);
    }

    /**
     * DELETE; Fehlerstatus kommt als Response zurück.
     *
     * @param  array<string, mixed>  $options
     */
    public function deleteResponse(string $url, array $options = []): Response {
        return $this->send('delete', $url, $options);
    }

    /**
     * Abruf einer vom Server genannten Folge-URL (Paginierung, Delta) — nur
     * auf dem Host der Verbindung. Der Abruf trägt die Zugangsdaten; eine
     * Folge-URL auf einen anderen Host würde sie dorthin senden
     * (Sicherheitsaudit 2026-10-04, sf-4).
     *
     * @param  array<string, mixed>  $options
     */
    public function getFollowUp(string $url, array $options = []): Response {
        if (! $this->staysOnBaseHost($url)) {
            throw new PluginApiException('Folge-URL zeigt auf einen anderen Host als die Verbindung.', 0, (string) parse_url($url, PHP_URL_HOST));
        }

        return $this->getResponse($url, [], $options);
    }

    /** Relative Pfade bleiben auf der Verbindung; absolute URLs müssen Schema, Host und Port der Basis-URL tragen. */
    private function staysOnBaseHost(string $url): bool {
        $target = parse_url(trim($url));
        if ($target === false) {
            return false;
        }
        if (! isset($target['scheme']) && ! isset($target['host'])) {
            return true;
        }
        $base = parse_url($this->getBaseUrl());
        if ($base === false || ! isset($target['scheme'], $target['host'], $base['scheme'], $base['host'])) {
            return false;
        }
        $port = static fn(array $parts): int => (int) ($parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80));

        return strtolower($target['scheme']) === strtolower($base['scheme'])
            && strtolower($target['host']) === strtolower($base['host'])
            && $port($target) === $port($base);
    }

    /**
     * Generischer Request für Sonderfälle (Multipart-Upload, abweichende
     * Accept-Header, Raw-Body) und beliebige Verben inkl. WebDAV/CalDAV
     * (PROPFIND, REPORT, MKCOL, MOVE, …; api-toolkit ≥ v2.9.2 stuft sie
     * idempotent ein und retryt sie); Fehlerstatus kommt als Response zurück.
     *
     * @param  array<string, mixed>  $options  Guzzle-Optionen (z. B. ['multipart' => [...]])
     */
    public function requestResponse(string $method, string $url, array $options = []): Response {
        return $this->send($method, $url, $options);
    }

    /**
     * Führt den Toolkit-Request aus und brückt PSR-7 auf die Laravel-Response.
     * Typisierte HTTP-Exceptions des Toolkits (4xx/5xx) werden — sofern sie
     * die Antwort tragen — in eine reguläre Response zurückverwandelt, damit
     * die Plugins ihre bestehende `successful()`-Fehlerbehandlung behalten.
     *
     * @param  array<string, mixed>  $options
     */
    protected function send(string $method, string $url, array $options): Response {
        $options = $this->withPinnedResolution($options);

        try {
            // request() statt Verb-Methoden: trägt auch WebDAV-Verben durch
            // dieselbe Pipeline (Throttle, Auth, methodenbewusster Retry).
            $psrResponse = $this->request($method, $url, $options);
        } catch (ApiException $e) {
            $psrResponse = $e->getResponse();
            if ($psrResponse === null) {
                throw $e;
            }
        }

        return new Response($psrResponse);
    }

    /**
     * Bindet den Verbindungsaufbau an die geprüften Adressen des Basis-Hosts
     * (Sicherheitsaudit 2026-10-04, sf-3): die Fabrik prüft das Ziel beim Bau,
     * verbunden wird später — dazwischen kann der Name auf eine interne
     * Adresse wechseln. Tolerant gegenüber DNS-Störungen (Entscheidung
     * 2026-10-05): löst der Host gerade nicht auf, läuft der Abruf ungebunden
     * und wird protokolliert; zeigt er nach innen, unterbleibt er.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function withPinnedResolution(array $options): array {
        if (! $this->pinsConnections) {
            return $options;
        }
        if (! $this->pinResolved) {
            $unresolved = false;
            $this->pin = UrlSafety::tolerantPinnedResolution($this->getBaseUrl(), $unresolved);
            $this->pinResolved = true;
            if ($unresolved) {
                Log::warning('Plugin-Abruf ohne Adressbindung: der Host löst gerade nicht auf.', ['host' => (string) parse_url($this->getBaseUrl(), PHP_URL_HOST)]);
            }
        }
        if ($this->pin === null) {
            throw new PluginApiException('Das Ziel der Verbindung zeigt auf eine interne Adresse — Abruf unterlassen.', 0, (string) parse_url($this->getBaseUrl(), PHP_URL_HOST));
        }
        if ($this->pin !== []) {
            $curl = is_array($options['curl'] ?? null) ? $options['curl'] : [];
            $options['curl'] = $curl + [CURLOPT_RESOLVE => $this->pin];
        }

        return $options;
    }

    /**
     * Jede Weiterleitung erneut durch die SSRF-Schranke (Sicherheitsaudit
     * 2026-09-17, ssrf-1): geprüft wurde bisher nur die Basis-URL, Guzzle
     * folgte danach jedem `Location` — auch auf 169.254.169.254 oder interne
     * Dienste. Nur http(s); private Ziele nur mit Opt-in des Plugins.
     *
     * @return array<string, mixed>
     */
    protected function buildClientConfig(): array {
        $config = parent::buildClientConfig();
        if (is_array($config['allow_redirects'] ?? null)) {
            $privateAllowed = $this->privateNetworkAllowed;
            $config['allow_redirects']['on_redirect'] = static function ($request, $response, $uri) use ($privateAllowed): void {
                $target = (string) $uri;
                $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
                $allowed = $privateAllowed
                    ? in_array($scheme, ['http', 'https'], true)
                    : \App\Support\UrlSafety::isPubliclyRoutableHttpUrl($target);
                if (! $allowed) {
                    throw new \GuzzleHttp\Exception\RequestException('Weiterleitung auf ein nicht erlaubtes Ziel blockiert.', $request, $response);
                }
            };
        }

        return $config;
    }
}
