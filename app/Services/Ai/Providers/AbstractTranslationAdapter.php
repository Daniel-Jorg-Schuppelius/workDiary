<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractTranslationAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use APIToolkit\Exceptions\{ApiException, TooManyRequestsException};
use App\Models\Ai\AiProviderConnection;
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Ai\Contracts\TranslationProviderInterface;
use App\Services\Ai\Dto\{AiTranslationResult, AiUsage, GlossaryEntry, TranslateRequest};
use App\Services\Ai\Exceptions\AiProviderCallException;
use GuzzleHttp\Client as GuzzleClient;
use TranslationToolkit\Entities\{GlossaryEntry as ToolkitGlossaryEntry, TranslateOptions};
use TranslationToolkit\Enums\{Formality, TextFormat};
use TranslationToolkit\Exceptions\TranslationException;
use TranslationToolkit\Providers\AbstractHttpTranslationProvider;

/**
 * Naht zwischen der KI-Familie „Übersetzung" (Feature 025) und dem
 * php-translation-toolkit: übersetzt `AiProviderConnection` +
 * {@see TranslateRequest} in einen Toolkit-Aufruf und dessen Ergebnis bzw.
 * Fehler zurück in die App-Verträge.
 *
 * Warum hier trotzdem Code steht (und das keine dünne Fassade ist): das
 * Toolkit kennt weder Verbindungen noch Budget, Gedächtnis-Glossare oder die
 * Redaktionspflicht. Genau diese Übersetzungsarbeit — inklusive
 * Tarif-Geschäftsregeln wie der DeepL-Free-Sperre — bleibt app-seitig.
 */
abstract class AbstractTranslationAdapter implements TranslationProviderInterface {
    private ?AbstractHttpTranslationProvider $provider = null;

    public function __construct(protected readonly AiProviderConnection $connection) {}

    /** Basis-URL des Providers (Verbindungs-Override vor Default). */
    abstract protected function baseUrl(): string;

    /**
     * Baut den Toolkit-Provider. `$transport` ist produktiv `null` (das
     * Toolkit baut Guzzle selbst), im Test der Mock-Handler aus
     * {@see \Tests\Support\FakePluginHttp}.
     */
    abstract protected function makeProvider(?GuzzleClient $transport): AbstractHttpTranslationProvider;

    protected function providerName(): string {
        return $this->connection->provider->value;
    }

    protected function provider(): AbstractHttpTranslationProvider {
        return $this->provider ??= app(PluginHttpFactory::class)->sdkClient(
            'ai-' . $this->providerName(),
            $this->baseUrl(),
            fn (?GuzzleClient $transport): AbstractHttpTranslationProvider => $this->makeProvider($transport),
        );
    }

    public function preflight(): void {
        $this->call(function (): null {
            $this->provider()->preflight();

            return null;
        });
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $result = $this->call(fn () => $this->provider()->translate(
            $request->text,
            $request->targetLanguage,
            $this->sourceLanguage($request),
            $this->options($request),
        ));

        return new AiTranslationResult(
            text: $result->text,
            deterministicTerminology: $result->deterministicTerminology,
            detectedSourceLanguage: $result->detectedSourceLang,
            usage: new AiUsage(characters: $result->charCount),
        );
    }

    /**
     * Quellsprache für den Toolkit-Aufruf. Provider mit Auto-Erkennung
     * überschreiben das mit `null`-Durchreichung.
     */
    protected function sourceLanguage(TranslateRequest $request): ?string {
        return $request->sourceLanguage;
    }

    protected function options(TranslateRequest $request): TranslateOptions {
        return new TranslateOptions(
            format: $request->format === 'html' ? TextFormat::Html : TextFormat::Text,
            formality: match ($request->formality) {
                'more' => Formality::More,
                'less' => Formality::Less,
                default => Formality::Default,
            },
            glossary: array_values(array_map(
                static fn (GlossaryEntry $e): ToolkitGlossaryEntry => new ToolkitGlossaryEntry($e->term, (string) $e->translation),
                // Ohne Zielübersetzung ist ein Gedächtnis-Begriff für das
                // Übersetzen wertlos — die Bedeutung hilft nur LLM-Providern.
                array_filter($request->glossary, static fn (GlossaryEntry $e): bool => filled($e->translation))
            )),
        );
    }

    /**
     * Führt einen Toolkit-Aufruf aus und übersetzt dessen
     * {@see TranslationException} in den einzigen Fehlerkanal der Adapter.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    protected function call(callable $call): mixed {
        try {
            return $call();
        } catch (TranslationException $e) {
            throw $this->mapException($e);
        }
    }

    /**
     * Fehlerdisziplin wie bisher: nach außen nur Status + Endpunkt, nie
     * Quelltexte, nie Schlüssel. Die Toolkit-Meldung wird bewusst **nicht**
     * durchgereicht — sie trägt bei 400/422 den Anbieter-Klartext, der den
     * fehlerhaften Feldinhalt zitieren kann.
     */
    protected function mapException(TranslationException $e): AiProviderCallException {
        $status = $e->getCode();
        $url = $this->baseUrl();

        if ($status <= 0) {
            // Ohne HTTP-Status und ohne zugrunde liegende Transport-Exception
            // ist es ein Prüf-/Protokollfehler des Toolkits (nicht unterstützte
            // Zielsprache, unerwartete Antwortform). „Transportfehler" würde
            // hier auf die falsche Fährte führen; diese Meldungen tragen nur
            // Konfigurations- und Formangaben, nie Quelltexte.
            if ($e->getPrevious() === null) {
                return AiProviderCallException::transport(
                    $this->providerName(),
                    (string) __('ai.error.technical', ['message' => $e->getMessage()])
                );
            }

            return AiProviderCallException::transport(
                $this->providerName(),
                (string) __('ai.error.transport', ['url' => $url])
            );
        }

        return AiProviderCallException::transport(
            $this->providerName(),
            (string) __('ai.error.http_status', ['status' => $status, 'url' => $url])
                . self::providerDetail($e)
        );
    }

    /**
     * Klartext des Anbieters zum Fehler — „HTTP 429" allein schickt einen auf
     * die falsche Fährte (Warten hilft nicht, wenn das Kontingent leer ist).
     * Der Fehlercode ist maschinell und immer unbedenklich.
     */
    protected static function providerDetail(TranslationException $e): string {
        $previous = $e->getPrevious();
        if (!$previous instanceof ApiException) {
            return '';
        }

        $response = $previous->getResponse();
        if ($response !== null && TooManyRequestsException::isQuotaResponse($response)) {
            return ' — ' . (string) __('ai.error.provider_quota');
        }

        $code = ApiException::errorCodesOf($response)[0] ?? '';

        return $code === '' ? '' : ' — ' . $code;
    }

    protected function requireApiKey(): string {
        $key = (string) $this->connection->api_key;
        if ($key === '') {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.api_key_missing'));
        }

        return $key;
    }
}
