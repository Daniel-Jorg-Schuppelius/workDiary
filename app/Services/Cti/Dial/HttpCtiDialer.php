<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HttpCtiDialer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti\Dial;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\CtiConnection;
use App\Plugins\Support\PluginHttpFactory;

/**
 * Click-to-Dial über die REST-APIs der unterstützten Anlagen (W4.5).
 *
 * Die drei Anbieter unterscheiden sich nur in Endpunkt und Feldnamen, das
 * Verfahren ist identisch (erst die eigene Durchwahl klingeln lassen, dann
 * zum Ziel verbinden). Deshalb ein Adapter mit providerabhängiger Anfrage
 * statt drei fast gleicher Klassen — die Normalizer-Familie trennt dagegen
 * zu Recht, weil dort die Nutzdaten wirklich verschieden sind.
 *
 * Transport über die {@see PluginHttpFactory} (php-api-toolkit: Retry/Backoff,
 * Secrets-Redaktion im Log); Tests ersetzen sie durch FakePluginHttp.
 */
class HttpCtiDialer implements CtiDialer {
    /** Standard-Basis-URLs, wenn an der Anbindung nichts hinterlegt ist. */
    private const DEFAULT_BASE_URLS = [
        'sipgate' => 'https://api.sipgate.com/v2',
        'placetel' => 'https://api.placetel.de/v2',
    ];

    public function dial(CtiConnection $connection, string $targetE164): void {
        $token = (string) ($connection->api_token ?? '');
        $extension = trim((string) ($connection->dial_extension ?? ''));
        if ($token === '' || $extension === '') {
            throw new CtiDialException((string) __('cti.dial.not_configured'));
        }

        $baseUrl = rtrim((string) ($connection->api_base_url ?: (self::DEFAULT_BASE_URLS[$connection->provider] ?? '')), '/');
        if ($baseUrl === '') {
            // STARFACE und generische Anlagen sind selbst gehostet: ohne
            // Basis-URL gibt es keinen sinnvollen Standard.
            throw new CtiDialException((string) __('cti.dial.no_base_url'));
        }

        [$path, $payload] = $this->request($connection->provider, $extension, $targetE164);

        $client = app(PluginHttpFactory::class)->client('cti', $baseUrl);
        $client->setAuthentication(new BearerAuthentication($token));

        $response = $client->postJson($baseUrl . $path, $payload);
        if (! $response->successful()) {
            throw new CtiDialException((string) __('cti.dial.rejected', ['status' => $response->status()]));
        }
    }

    /**
     * Endpunkt + Nutzdaten je Anlage.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function request(string $provider, string $extension, string $targetE164): array {
        return match ($provider) {
            // sipgate.io: /sessions/calls, "caller" ist die eigene Durchwahl.
            'sipgate' => ['/sessions/calls', [
                'deviceId' => $extension,
                'caller' => $extension,
                'callee' => $targetE164,
            ]],
            // Placetel: Click-to-Dial über /calls.
            'placetel' => ['/calls', [
                'from' => $extension,
                'to' => $targetE164,
            ]],
            // STARFACE und generisch: eigener Endpunkt an der Instanz.
            default => ['/calls', [
                'extension' => $extension,
                'number' => $targetE164,
            ]],
        };
    }
}
