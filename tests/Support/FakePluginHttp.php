<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakePluginHttp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsGrant;
use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Closure;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Promise\{Create, PromiseInterface};
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * `Http::fake()`-Ersatz für die auf `php-api-toolkit`/Guzzle migrierten
 * Plugin-Clients: ersetzt die {@see PluginHttpFactory} im Container durch
 * eine Variante, deren Clients gegen einen Mock-Handler sprechen.
 *
 *     $fake = FakePluginHttp::fake([
 *         'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response(['email' => 'x@y.z']),
 *         'https://api.track.toggl.com/*'          => FakePluginHttp::response([], 404),
 *     ]);
 *     // ... Code ausführen ...
 *     $fake->assertSent(fn (RequestInterface $r) => str_contains((string) $r->getUri(), '/me'));
 *
 * Stub-Werte je URL-Pattern (erste Übereinstimmung gewinnt, `*`-Wildcards
 * wie bei `Str::is()`):
 * - {@see Psr7Response} — wird für jeden Treffer geliefert;
 * - `array`/`string` — JSON-/Text-Body mit Status 200;
 * - `Closure(RequestInterface): Psr7Response` — dynamische Antwort;
 * - Liste von Responses — Sequenz; die letzte Antwort wiederholt sich.
 *
 * Ohne Stubs (oder ohne Treffer) antwortet der Fake mit `200 {}` — wie
 * `Http::fake()` ohne Argumente. Retry-Wartezeiten sind im Fake auf 0
 * gesetzt, damit 429-/Retry-Tests nicht schlafen.
 */
class FakePluginHttp extends PluginHttpFactory {
    /** @var array<string, Psr7Response|array<mixed>|string|Closure|list<Psr7Response>> */
    protected array $stubs = [];

    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    protected array $recorded = [];

    /** @var array<string, int> Sequenz-Positionen je Pattern */
    protected array $sequenceIndex = [];

    /**
     * @param  array<string, mixed>  $stubs  URL-Pattern → Stub (siehe Klassen-Doku)
     */
    public static function fake(array $stubs = []): self {
        $fake = new self();
        $fake->stubs = $stubs;

        app()->instance(PluginHttpFactory::class, $fake);

        return $fake;
    }

    /**
     * JSON-/Text-Response-Helfer analog `Http::response()`.
     *
     * @param  array<mixed>|string|null  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array|string|null $body = null, int $status = 200, array $headers = []): Psr7Response {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        }

        return new Psr7Response($status, $headers, $body ?? '');
    }

    public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0): PluginApiClient {
        $client = new PluginApiClient($pluginId, $baseUrl, $this->mockedGuzzle($baseUrl));

        // Tests sollen bei Retry-Pfaden (429/503) und Tarif-Drosseln
        // (requestInterval) nicht real schlafen.
        $client->setBaseRetryDelay(0);
        $client->setMaxRetryDelay(0);

        return $client;
    }

    /** Provider-SDK-Client gegen den Mock-Handler statt gegen die echte API. */
    public function sdkClient(string $pluginId, string $baseUrl, callable $make): ClientAbstract {
        $client = parent::sdkClient($pluginId, $baseUrl, $make);
        $client->setBaseRetryDelay(0);
        $client->setMaxRetryDelay(0);

        return $client;
    }

    protected function sdkTransport(string $baseUrl): GuzzleClient {
        return $this->mockedGuzzle($baseUrl);
    }

    public function clientCredentialsGrant(string $pluginId, string $clientId, string $clientSecret, string $tokenUrl): OAuth2ClientCredentialsGrant {
        $grant = $this->configureGrant(
            new OAuth2ClientCredentialsGrant($clientId, $clientSecret, $tokenUrl, null, $this->mockedGuzzle($tokenUrl)),
            $pluginId,
        );

        // Auch Token-Endpunkt-Retries (429/503) sollen nicht real schlafen.
        $grant->setBaseRetryDelay(0);
        $grant->setMaxRetryDelay(0);

        return $grant;
    }

    /** Guzzle-Client gegen den aufzeichnenden Mock-Handler (Stubs siehe Klassen-Doku). */
    protected function mockedGuzzle(string $baseUri): GuzzleClient {
        $handler = HandlerStack::create(function (RequestInterface $request, array $options): PromiseInterface {
            $this->recorded[] = ['request' => $request, 'options' => $options];

            return Create::promiseFor($this->respondTo($request));
        });

        return new GuzzleClient([
            'base_uri' => $baseUri,
            'handler' => $handler,
        ]);
    }

    protected function respondTo(RequestInterface $request): Psr7Response {
        $url = (string) $request->getUri();

        foreach ($this->stubs as $pattern => $stub) {
            // Wie Http::fake(): nur ein führendes Wildcard ergänzen, damit
            // schemalose Patterns matchen, exakte Patterns aber exakt bleiben.
            if (! Str::is(Str::start($pattern, '*'), $url)) {
                continue;
            }

            if ($stub instanceof Closure) {
                return $stub($request);
            }

            if ($stub instanceof Psr7Response) {
                return $stub;
            }

            if (is_array($stub) && $stub !== [] && array_is_list($stub) && $stub[0] instanceof Psr7Response) {
                $index = $this->sequenceIndex[$pattern] ?? 0;
                $this->sequenceIndex[$pattern] = $index + 1;

                return $stub[min($index, count($stub) - 1)];
            }

            return self::response($stub);
        }

        return self::response([]);
    }

    /** @return array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    public function recorded(): array {
        return $this->recorded;
    }

    /** @param Closure(RequestInterface, array<string, mixed>): bool $matcher */
    public function assertSent(Closure $matcher): void {
        foreach ($this->recorded as $entry) {
            if ($matcher($entry['request'], $entry['options'])) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail('Kein aufgezeichneter Plugin-HTTP-Request entspricht dem Matcher.');
    }

    /** @param Closure(RequestInterface, array<string, mixed>): bool $matcher */
    public function assertNotSent(Closure $matcher): void {
        foreach ($this->recorded as $entry) {
            if ($matcher($entry['request'], $entry['options'])) {
                Assert::fail('Ein aufgezeichneter Plugin-HTTP-Request entspricht dem Matcher, sollte aber nicht gesendet werden.');
            }
        }

        Assert::assertTrue(true);
    }

    public function assertNothingSent(): void {
        Assert::assertSame([], $this->recorded, 'Es wurden Plugin-HTTP-Requests gesendet.');
    }

    public function assertSentCount(int $count): void {
        Assert::assertCount($count, $this->recorded);
    }
}
