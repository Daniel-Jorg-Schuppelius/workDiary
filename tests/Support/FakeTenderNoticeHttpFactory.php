<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeTenderNoticeHttpFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * Ersetzt den Guzzle-Transport des Bekanntmachungs-Abrufs durch eine
 * MockHandler-Queue.
 *
 * `Http::fake()` greift hier **nicht**: Der `PluginApiClient` fährt über Guzzle,
 * nicht über Laravels HTTP-Fassade. Ohne diese Fabrik holt ein Test echte Daten
 * vom Bundesdienst — das ist einmal passiert.
 */
final class FakeTenderNoticeHttpFactory extends PluginHttpFactory {
    /** @param list<Response> $responses */
    public function __construct(private readonly array $responses) {}

    public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0): PluginApiClient {
        $stack = HandlerStack::create(new MockHandler($this->responses));

        return new PluginApiClient($pluginId, $baseUrl, new Client(['handler' => $stack]));
    }

    public static function zip(string $body): Response {
        return new Response(200, ['Content-Type' => 'application/zip'], $body);
    }
}
