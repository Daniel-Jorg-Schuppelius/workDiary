<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslationProvidersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Contracts\TranslationProviderInterface;
use App\Services\Ai\Dto\{GlossaryEntry, TranslateRequest};
use App\Services\Ai\Exceptions\AiProviderCallException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Contract-Tests der Übersetzungs-Familie (Feature 025, MVP-409/410):
 * DeepL (v3-Glossar-Sync, Free-Tarif-Sperre), LibreTranslate
 * (Platzhalter-Terminologie) und Azure Translator (Dynamic Dictionary) —
 * jeweils deterministische Terminologie und redigiertes Fehler-Mapping.
 */
class TranslationProvidersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function provider(AiProviderConnection $connection): TranslationProviderInterface {
        $provider = (new AiProviderFactory)->make($connection);
        assert($provider instanceof TranslationProviderInterface);

        return $provider;
    }

    private function request(): TranslateRequest {
        return new TranslateRequest(
            text: 'Der Wartungsvertrag wurde verlängert.',
            targetLanguage: 'en',
            sourceLanguage: 'de',
            glossary: [new GlossaryEntry('Wartungsvertrag', 'jährlicher Servicevertrag', 'maintenance agreement')],
        );
    }

    // ── DeepL ────────────────────────────────────────────────────────

    private function deeplConnection(string $key = 'deepl-pro-key'): AiProviderConnection {
        return AiProviderConnection::factory()->translation()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::DeepL,
            'api_key' => $key,
        ]);
    }

    public function test_deepl_creates_glossary_and_translates_deterministically(): void {
        $fake = FakePluginHttp::fake([
            'https://api.deepl.com/v3/glossaries' => ['glossary_id' => 'g-123'],
            'https://api.deepl.com/v2/translate*' => [
                'translations' => [['text' => 'The maintenance agreement was extended.', 'detected_source_language' => 'DE']],
            ],
        ]);

        $connection = $this->deeplConnection();
        $result = $this->provider($connection)->translate($this->request());

        $this->assertTrue($result->deterministicTerminology);
        $this->assertSame('The maintenance agreement was extended.', $result->text);
        $this->assertSame('g-123', data_get($connection->fresh()->options, 'deepl_glossary_id'));
        $fake->assertSent(function (RequestInterface $r): bool {
            return str_contains((string) $r->getUri(), '/v2/translate')
                && str_contains((string) $r->getBody(), 'g-123')
                && ($r->getHeader('Authorization')[0] ?? '') === 'DeepL-Auth-Key deepl-pro-key';
        });
    }

    public function test_deepl_reuses_existing_glossary_via_dictionary_replace(): void {
        $connection = $this->deeplConnection();
        $connection->forceFill(['options' => ['deepl_glossary_id' => 'g-existing']])->save();

        $fake = FakePluginHttp::fake([
            'https://api.deepl.com/v3/glossaries/g-existing/dictionaries*' => ['source_lang' => 'DE'],
            'https://api.deepl.com/v2/translate*' => ['translations' => [['text' => 'ok']]],
        ]);

        $this->provider($connection->fresh())->translate($this->request());

        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/v3/glossaries/g-existing/dictionaries'));
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_ends_with(rtrim((string) $r->getUri(), '/'), '/v3/glossaries'));
    }

    public function test_deepl_without_glossary_translations_skips_glossary(): void {
        $fake = FakePluginHttp::fake([
            'https://api.deepl.com/v2/translate*' => ['translations' => [['text' => 'ok']]],
        ]);

        $result = $this->provider($this->deeplConnection())->translate(new TranslateRequest(
            text: 'Hallo',
            targetLanguage: 'en',
        ));

        $this->assertFalse($result->deterministicTerminology);
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/v3/glossaries'));
    }

    public function test_deepl_free_keys_are_rejected(): void {
        FakePluginHttp::fake();

        $this->expectException(AiProviderCallException::class);
        $this->expectExceptionMessageMatches('/Free/');

        $this->provider($this->deeplConnection('freikey:fx'))->translate($this->request());
    }

    public function test_deepl_formality_and_html_are_passed(): void {
        $captured = '';
        FakePluginHttp::fake([
            'https://api.deepl.com/v2/translate*' => function (RequestInterface $r) use (&$captured): Psr7Response {
                $captured = (string) $r->getBody();

                return FakePluginHttp::response(['translations' => [['text' => 'ok']]]);
            },
        ]);

        $this->provider($this->deeplConnection())->translate(new TranslateRequest(
            text: '<p>Hallo</p>',
            targetLanguage: 'en',
            format: 'html',
            formality: 'more',
        ));

        $this->assertStringContainsString('prefer_more', $captured);
        $this->assertStringContainsString('html', $captured);
    }

    // ── LibreTranslate ───────────────────────────────────────────────

    public function test_libretranslate_enforces_terminology_via_placeholders(): void {
        $connection = AiProviderConnection::factory()->translation()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::LibreTranslate,
            'base_url' => 'http://libre.intern:5000',
            'api_key' => null,
        ]);

        FakePluginHttp::fake([
            'http://libre.intern:5000/translate*' => function (RequestInterface $r): Psr7Response {
                $body = json_decode((string) $r->getBody(), true);
                // Begriff muss maskiert ankommen …
                assert(str_contains((string) $body['q'], 'WDTERM0X'));

                // … und der Fake „übersetzt" um den Token herum.
                return FakePluginHttp::response([
                    'translatedText' => 'The WDTERM0X was extended.',
                    'detectedLanguage' => ['language' => 'de'],
                ]);
            },
        ]);

        $result = $this->provider($connection)->translate($this->request());

        $this->assertTrue($result->deterministicTerminology);
        $this->assertSame('The maintenance agreement was extended.', $result->text);
    }

    // ── Azure Translator ─────────────────────────────────────────────

    public function test_azure_translator_uses_dynamic_dictionary_markup(): void {
        $connection = AiProviderConnection::factory()->translation()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::AzureTranslator,
            'api_key' => 'az-key',
            'options' => ['region' => 'germanywestcentral'],
        ]);

        $captured = '';
        $fake = FakePluginHttp::fake([
            'https://api.cognitive.microsofttranslator.com/translate*' => function (RequestInterface $r) use (&$captured): Psr7Response {
                $captured = (string) $r->getBody();

                return FakePluginHttp::response([[
                    'translations' => [['text' => 'The maintenance agreement was extended.']],
                    'detectedLanguage' => ['language' => 'de'],
                ]]);
            },
        ]);

        $result = $this->provider($connection)->translate($this->request());

        $this->assertTrue($result->deterministicTerminology);
        $this->assertSame('The maintenance agreement was extended.', $result->text);
        $this->assertStringContainsString('mstrans:dictionary', $captured);
        $this->assertStringContainsString('maintenance agreement', $captured);
        $fake->assertSent(function (RequestInterface $r): bool {
            return ($r->getHeader('Ocp-Apim-Subscription-Key')[0] ?? '') === 'az-key'
                && ($r->getHeader('Ocp-Apim-Subscription-Region')[0] ?? '') === 'germanywestcentral'
                && str_contains((string) $r->getUri(), 'textType=html');
        });
    }

    public function test_translation_http_error_maps_without_text_leak(): void {
        FakePluginHttp::fake([
            'https://api.deepl.com/*' => FakePluginHttp::response(['message' => 'quota'], 456),
        ]);

        try {
            $this->provider($this->deeplConnection())->translate(new TranslateRequest(
                text: 'GeheimerBelegtext',
                targetLanguage: 'en',
            ));
            $this->fail('HTTP-Fehler wurde nicht gemappt.');
        } catch (AiProviderCallException $e) {
            $this->assertStringNotContainsString('GeheimerBelegtext', $e->getMessage());
        }
    }
}
