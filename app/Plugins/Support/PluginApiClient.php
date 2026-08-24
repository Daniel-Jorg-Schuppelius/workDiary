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
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\Response;

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
    public function __construct(string $pluginId, string $baseUrl, ?GuzzleClient $httpClient = null) {
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
}
