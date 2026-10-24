<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CallsJsonRpc.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * JSON-RPC-2.0-Aufruf über POST für Plugin-Clients (Kanboard, Leantime), nur
 * für lesende Methoden: das Toolkit darf ihn deshalb bei 429/503 wiederholen.
 * Die nutzende Klasse bringt {@see GuardsPluginApiResponses} mit: HTTP-Fehler
 * laufen über `guard()`, ein `error`-Objekt im Body wirft die Provider-Exception.
 */
trait CallsJsonRpc {
    private int $jsonRpcId = 0;

    /**
     * @param  array<string, mixed>  $params
     * @param  bool  $allowForbidden  HTTP 403 als „nicht sichtbar“ (null) statt als Fehler
     */
    protected function jsonRpc(PluginApiClient $api, string $url, string $method, array $params = [], bool $allowForbidden = false): mixed {
        $response = $api->postJson($url, ['jsonrpc' => '2.0', 'method' => $method, 'id' => ++$this->jsonRpcId, 'params' => $params], ['retry_non_idempotent' => true]);
        if ($allowForbidden && $response->status() === 403) {
            return null;
        }
        $body = $this->guard($response, $method);
        $class = $this->apiExceptionClass();
        if (is_array($body['error'] ?? null)) {
            $message = is_string($body['error']['message'] ?? null) ? $body['error']['message'] : 'error';
            $code = $body['error']['code'] ?? null;

            // JSON-RPC-Fehler kommen meist mit HTTP 200 — ein Status würde einen Erfolg vortäuschen.
            throw new $class(sprintf('%s %s: %s', $this->apiLabel(), $method, mb_substr($message, 0, 300)), 0, $method, is_scalar($code) ? (string) $code : null);
        }
        if (! array_key_exists('result', $body)) {
            throw new $class(sprintf('%s %s: Antwort ohne result', $this->apiLabel(), $method), 0, $method);
        }

        return $body['result'];
    }
}
