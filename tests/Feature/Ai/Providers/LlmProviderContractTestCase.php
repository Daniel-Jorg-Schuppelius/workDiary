<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LlmProviderContractTestCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Dto\{ClassifyRequest, ExamplePair, FormulateRequest, GlossaryEntry, TranslateRequest};
use App\Services\Ai\Exceptions\AiProviderCallException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Contract-Test der LLM-Familie (Feature 025, MVP-411): JEDER
 * LLM-Adapter besteht exakt dieselben Prüfungen — Verben, Prompt-Regeln
 * (Nicht-Erfinden, Glossar, Beispiele), Katalog-Bindung, Fehler-Mapping
 * ohne Prompt-Leak, Retry-Semantik und Preflight. Konkrete Adapter
 * liefern nur Verbindung, Endpunkt-Muster und Wire-Format.
 */
abstract class LlmProviderContractTestCase extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** Verbindung mit providerspezifischer Konfiguration. */
    abstract protected function connection(): AiProviderConnection;

    /** URL-Pattern des Completion-Endpunkts (FakePluginHttp-Glob). */
    abstract protected function completionUrlPattern(): string;

    /** URL-Pattern des Preflight-Endpunkts. */
    abstract protected function preflightUrlPattern(): string;

    /** Wire-Format einer Erfolgsantwort mit Text + Verbrauch. */
    abstract protected function successBody(string $text): array|string;

    /** Prüft den Auth-Header des aufgezeichneten Requests. */
    abstract protected function hasAuthHeader(RequestInterface $request): bool;

    protected function provider(AiProviderConnection $connection): LlmProviderInterface {
        $provider = (new AiProviderFactory)->make($connection);
        assert($provider instanceof LlmProviderInterface);

        return $provider;
    }

    public function test_formulate_returns_text_with_usage_and_auth(): void {
        $fake = FakePluginHttp::fake([
            $this->completionUrlPattern() => $this->successBody('Wartung der Client- und Server-Systeme'),
        ]);

        $result = $this->provider($this->connection())->formulate(new FormulateRequest(text: 'wartung clients server'));

        $this->assertSame('Wartung der Client- und Server-Systeme', $result->text);
        $this->assertGreaterThan(0, $result->usage->inputTokens + $result->usage->outputTokens);
        $fake->assertSent(fn (RequestInterface $r): bool => $this->hasAuthHeader($r));
    }

    public function test_formulate_prompt_carries_rules_glossary_and_examples(): void {
        $captured = '';
        FakePluginHttp::fake([
            $this->completionUrlPattern() => function (RequestInterface $request) use (&$captured): Psr7Response {
                $captured = (string) $request->getBody();

                return FakePluginHttp::response($this->successBody('ok'));
            },
        ]);

        $this->provider($this->connection())->formulate(new FormulateRequest(
            text: 'snap update eingespielt',
            styleRules: ['Nominalstil verwenden'],
            glossary: [new GlossaryEntry('snap', 'Snap-Paketverwaltung der Ubuntu-Server')],
            examples: [new ExamplePair('wartung server', 'Wartung der Serversysteme')],
        ));

        $this->assertStringContainsString('rfinde keine', $captured); // Nicht-Erfinden-Regel
        $this->assertStringContainsString('Snap-Paketverwaltung', $captured);
        $this->assertStringContainsString('Nominalstil', $captured);
        $this->assertStringContainsString('Wartung der Serversysteme', $captured);
    }

    public function test_classify_returns_only_catalog_values(): void {
        FakePluginHttp::fake([
            $this->completionUrlPattern() => $this->successBody('["wartung", "halluziniert"]'),
        ]);

        $result = $this->provider($this->connection())->classify(new ClassifyRequest(
            text: 'Serverwartung durchgeführt',
            catalog: ['wartung', 'installation'],
        ));

        $this->assertSame(['wartung'], $result->onlyFromCatalog(['wartung', 'installation'])->values);
    }

    public function test_translate_via_prompt_is_probabilistic_and_carries_terminology(): void {
        $captured = '';
        FakePluginHttp::fake([
            $this->completionUrlPattern() => function (RequestInterface $request) use (&$captured): Psr7Response {
                $captured = (string) $request->getBody();

                return FakePluginHttp::response($this->successBody('Maintenance of the server systems'));
            },
        ]);

        $result = $this->provider($this->connection())->translate(new TranslateRequest(
            text: 'Wartung der Serversysteme',
            targetLanguage: 'en',
            glossary: [new GlossaryEntry('Wartungsvertrag', 'Servicevertrag', 'maintenance agreement')],
        ));

        $this->assertFalse($result->deterministicTerminology);
        $this->assertStringContainsString('maintenance agreement', $captured);
    }

    public function test_http_error_maps_to_call_exception_without_prompt_leak(): void {
        FakePluginHttp::fake([
            $this->completionUrlPattern() => FakePluginHttp::response(['error' => 'boom'], 500),
        ]);

        try {
            $this->provider($this->connection())->formulate(new FormulateRequest(text: 'GeheimerKundentext'));
            $this->fail('HTTP-Fehler wurde nicht gemappt.');
        } catch (AiProviderCallException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
            $this->assertStringNotContainsString('GeheimerKundentext', $e->getMessage());
        }
    }

    public function test_429_is_retried_and_then_succeeds(): void {
        FakePluginHttp::fake([
            $this->completionUrlPattern() => [
                FakePluginHttp::response(['error' => 'rate limited'], 429),
                FakePluginHttp::response($this->successBody('Nach Retry erfolgreich')),
            ],
        ]);

        $result = $this->provider($this->connection())->formulate(new FormulateRequest(text: 'x'));

        $this->assertSame('Nach Retry erfolgreich', $result->text);
    }

    public function test_preflight_ok_and_failure(): void {
        FakePluginHttp::fake([
            $this->preflightUrlPattern() => FakePluginHttp::response(['data' => []]),
        ]);
        $this->provider($this->connection())->preflight();

        FakePluginHttp::fake([
            $this->preflightUrlPattern() => FakePluginHttp::response(['error' => 'unauthorized'], 401),
        ]);
        $this->expectException(AiProviderCallException::class);
        $this->provider($this->connection())->preflight();
    }
}
