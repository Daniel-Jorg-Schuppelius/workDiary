<?php
/*
 * Created on   : Sat Jul 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderBaseUrlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Dto\FormulateRequest;
use App\Services\Ai\Exceptions\AiProviderCallException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Basis-URL + Endpunktpfad (Feature 025): Verbindungen werden in der
 * Praxis mit Pfadanteil eingetragen — vom Versionspräfix (`…/v1`) bis
 * zur kompletten Endpunkt-URL (`…/v1/responses`). Stures Anhängen ergab
 * `/v1/responses/v1/models` → 404 im Preflight (Produktionsbefund
 * 2026-07-25). Gateway-Präfixe müssen dabei erhalten bleiben.
 */
class ProviderBaseUrlTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function connection(AiProviderType $provider, ?string $baseUrl): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => $provider,
            'base_url' => $baseUrl,
            'api_key' => 'sk-test',
            'model' => 'test-model',
        ]);
    }

    private function provider(AiProviderConnection $connection): LlmProviderInterface {
        $provider = (new AiProviderFactory)->make($connection);
        assert($provider instanceof LlmProviderInterface);

        return $provider;
    }

    /** @return list<string> Aufgezeichnete Request-URLs ohne Query. */
    private function sentUrls(FakePluginHttp $fake): array {
        return array_values(array_map(
            fn (array $entry): string => explode('?', (string) $entry['request']->getUri(), 2)[0],
            $fake->recorded(),
        ));
    }

    /** @return iterable<string, array{AiProviderType, string|null, string}> */
    public static function preflightUrls(): iterable {
        yield 'openai ohne basis' => [AiProviderType::OpenAi, null, 'https://api.openai.com/v1/models'];
        yield 'openai mit versionspräfix' => [AiProviderType::OpenAi, 'https://api.openai.com/v1', 'https://api.openai.com/v1/models'];
        yield 'openai mit endpunkt-url' => [AiProviderType::OpenAi, 'https://api.openai.com/v1/responses', 'https://api.openai.com/v1/models'];
        yield 'openai hinter gateway' => [AiProviderType::OpenAi, 'https://gw.example.com/openai/v1', 'https://gw.example.com/openai/v1/models'];
        yield 'openai-kompatibel' => [AiProviderType::OpenAiCompatible, 'https://llm.example.com/v1', 'https://llm.example.com/v1/models'];
        yield 'azure ressource' => [AiProviderType::AzureOpenAi, 'https://res.openai.azure.com', 'https://res.openai.azure.com/openai/v1/models'];
        yield 'azure mit v1-pfad' => [AiProviderType::AzureOpenAi, 'https://res.openai.azure.com/openai/v1', 'https://res.openai.azure.com/openai/v1/models'];
        yield 'ollama default' => [AiProviderType::Ollama, null, 'http://localhost:11434/api/tags'];
        yield 'ollama mit api-pfad' => [AiProviderType::Ollama, 'http://ollama.local:11434/api', 'http://ollama.local:11434/api/tags'];
        yield 'anthropic mit endpunkt-url' => [AiProviderType::Anthropic, 'https://api.anthropic.com/v1/messages', 'https://api.anthropic.com/v1/models'];
    }

    #[DataProvider('preflightUrls')]
    public function test_preflight_hits_expected_url(AiProviderType $provider, ?string $baseUrl, string $expected): void {
        $fake = FakePluginHttp::fake([$expected . '*' => FakePluginHttp::response(['data' => []])]);

        $this->provider($this->connection($provider, $baseUrl))->preflight();

        $this->assertSame([$expected], $this->sentUrls($fake));
    }

    public function test_openai_completion_keeps_endpoint_base_url_intact(): void {
        $fake = FakePluginHttp::fake([
            'https://api.openai.com/v1/responses*' => FakePluginHttp::response([
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
        ]);

        $connection = $this->connection(AiProviderType::OpenAi, 'https://api.openai.com/v1/responses');
        $this->provider($connection)->formulate(new FormulateRequest(text: 'x'));

        $this->assertSame(['https://api.openai.com/v1/responses'], $this->sentUrls($fake));
    }

    public function test_error_message_names_effective_endpoint(): void {
        FakePluginHttp::fake(['*' => FakePluginHttp::response(['error' => 'nope'], 404)]);

        $connection = $this->connection(AiProviderType::OpenAi, 'https://api.openai.com/v1/responses');

        try {
            $this->provider($connection)->preflight();
            $this->fail('404 wurde nicht gemappt.');
        } catch (AiProviderCallException $e) {
            $this->assertStringContainsString('https://api.openai.com/v1/models', $e->getMessage());
            $this->assertStringNotContainsString('sk-test', $e->getMessage());
        }
    }

    public function test_fake_records_no_auth_key_in_url(): void {
        $fake = FakePluginHttp::fake(['*' => FakePluginHttp::response(['data' => []])]);

        $this->provider($this->connection(AiProviderType::Gemini, null))->preflight();

        $fake->assertSent(fn (RequestInterface $r): bool => ! str_contains((string) $r->getUri(), 'sk-test'));
    }
}
