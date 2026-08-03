<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AzureTranslatorProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\TranslationProviderInterface;
use App\Services\Ai\Dto\{AiTranslationResult, AiUsage, GlossaryEntry, TranslateRequest};
use App\Services\Ai\Exceptions\AiProviderCallException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Azure Translator (Feature 025, MVP-410): `POST /translate` (v3.0) mit
 * `Ocp-Apim-Subscription-Key` + Region (options.region). Terminologie
 * deterministisch über Dynamic-Dictionary-Markup je Request
 * (`<mstrans:dictionary translation="…">Begriff</mstrans:dictionary>`)
 * direkt aus dem KI-Gedächtnis — kein Glossar-Sync nötig. „No-Trace":
 * Texte werden laut Microsoft nicht persistiert.
 */
class AzureTranslatorProvider extends AbstractHttpAiProvider implements TranslationProviderInterface {
    private const API_VERSION = '3.0';

    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.cognitive.microsofttranslator.com', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        $headers = ['Ocp-Apim-Subscription-Key' => $this->requireApiKey()];

        $region = (string) data_get($this->connection->options, 'region', '');
        if ($region !== '') {
            $headers['Ocp-Apim-Subscription-Region'] = $region;
        }

        return $headers;
    }

    public function preflight(): void {
        // Billigster echter Aufruf mit Schlüsselprüfung (4 Zeichen).
        $this->post($this->translatePath('en', null, false), [['Text' => 'ping']]);
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $entries = array_values(array_filter(
            $request->glossary,
            static fn (GlossaryEntry $e): bool => filled($e->translation)
        ));

        $text = $this->applyDynamicDictionary($request->text, $entries);

        $response = $this->post(
            $this->translatePath($request->targetLanguage, $request->sourceLanguage, $request->format === 'html' || $entries !== []),
            [['Text' => $text]]
        );

        return new AiTranslationResult(
            text: (string) $response->json('0.translations.0.text', ''),
            deterministicTerminology: $entries !== [],
            detectedSourceLanguage: $response->json('0.detectedLanguage.language'),
            usage: new AiUsage(characters: mb_strlen($request->text)),
        );
    }

    private function translatePath(string $target, ?string $source, bool $html): string {
        $query = [
            'api-version' => self::API_VERSION,
            'to' => strtolower($target),
        ];
        if ($source !== null) {
            $query['from'] = strtolower($source);
        }
        if ($html) {
            $query['textType'] = 'html';
        }

        return '/translate?' . http_build_query($query);
    }

    /**
     * Azure erwartet beim Body eine JSON-LISTE — postJson() der Basis
     * nimmt assoziative Payloads, daher hier direkt über den Client.
     *
     * @param list<array<string, string>> $body
     */
    private function post(string $path, array $body): Response {
        $url = $this->api()->buildUrl($path);

        try {
            $response = $this->api()->requestResponse('post', $url, ['json' => $body]);
        } catch (Throwable) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                (string) __('ai.error.transport', ['url' => self::redactUrl($url)])
            );
        }

        return $this->assertOk($response, $url);
    }

    /** @param list<GlossaryEntry> $entries */
    private function applyDynamicDictionary(string $text, array $entries): string {
        foreach ($entries as $entry) {
            $replaced = preg_replace(
                '/' . preg_quote($entry->term, '/') . '/iu',
                sprintf(
                    '<mstrans:dictionary translation="%s">%s</mstrans:dictionary>',
                    htmlspecialchars((string) $entry->translation, ENT_QUOTES),
                    $entry->term
                ),
                $text
            );
            if ($replaced !== null) {
                $text = $replaced;
            }
        }

        return $text;
    }
}
