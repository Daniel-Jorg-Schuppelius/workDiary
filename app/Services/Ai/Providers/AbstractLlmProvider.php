<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractLlmProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Dto\{AiClassificationResult, AiExtractionResult, AiFindResult, AiTextResult, AiTranslationResult, ClassifyRequest, ExamplePair, ExplainRequest, ExtractRequest, FindRequest, FormulateRequest, GlossaryEntry, SummarizeRequest, TranslateRequest};

/**
 * Gemeinsame Verb-Implementierung der LLM-Familie (Feature 025, MVP-407):
 * die Prompt-Verträge — inklusive der verbindlichen Nicht-Erfinden-Regel
 * beim Formulieren und der Katalog-Bindung beim Klassifizieren — sind für
 * ALLE LLM-Adapter identisch; die konkreten Adapter liefern nur noch den
 * providerspezifischen {@see complete()}-Aufruf. Klassifizieren wird
 * zusätzlich typseitig über
 * {@see AiClassificationResult::onlyFromCatalog()} abgesichert (der
 * Invocation-Service wendet den Katalog erneut an).
 */
abstract class AbstractLlmProvider extends AbstractHttpAiProvider implements LlmProviderInterface {
    protected const MAX_OUTPUT_TOKENS = 1500;

    /** Providerspezifischer Chat-Aufruf: System- + Nutzer-Prompt → Text. */
    abstract protected function complete(string $system, string $user, bool $expectJson = false): Completion;

    public function formulate(FormulateRequest $request): AiTextResult {
        $system = implode("\n", array_filter([
            'Du formulierst stichwortartige Arbeitsnotizen zu einem sauberen, kundentauglichen Text um (Sprache: ' . $request->language . ').',
            'VERBINDLICH: Verwende ausschließlich Informationen aus der Eingabe. Erfinde keine Leistungen, Mengen, Ergebnisse oder Fakten. Lasse nichts Wesentliches weg.',
            // Kundennamen-Regel (Feature 084 MVP-402, Vollaudit 2026-07 M35).
            'VERBINDLICH: Nenne niemals den Namen des Kunden oder Empfängers — formuliere neutral (z. B. „der Kunde"), auch wenn Namen in der Eingabe stehen.',
            'Antworte NUR mit dem umformulierten Text, ohne Anführungszeichen oder Erklärungen.',
            $this->styleBlock($request->styleRules),
            $this->glossaryBlock($request->glossary),
            $this->exampleBlock($request->examples),
            $request->contextHints !== [] ? 'Kontext: ' . implode('; ', $request->contextHints) : null,
        ]));

        $completion = $this->complete($system, $request->text);

        return new AiTextResult(trim($completion->text), $completion->usage);
    }

    public function summarize(SummarizeRequest $request): AiTextResult {
        $system = implode("\n", array_filter([
            'Du fasst mehrere Arbeitsnotizen zu EINEM zusammenhängenden, kundentauglichen Leistungstext zusammen (Sprache: ' . $request->language . ').',
            'VERBINDLICH: Nur Informationen aus den Einträgen verwenden — nichts erfinden, keine Einträge unterschlagen.',
            'VERBINDLICH: Nenne niemals den Namen des Kunden oder Empfängers — formuliere neutral (z. B. „der Kunde").',
            $request->period !== null ? 'Leistungszeitraum: ' . $request->period . ' (im Text nennen).' : null,
            'Antworte NUR mit dem zusammengefassten Text.',
            $this->styleBlock($request->styleRules),
            $this->glossaryBlock($request->glossary),
        ]));

        $user = implode("\n", array_map(
            static fn (string $item, int $i): string => ($i + 1) . '. ' . $item,
            $request->items,
            array_keys($request->items)
        ));

        $completion = $this->complete($system, $user);

        return new AiTextResult(trim($completion->text), $completion->usage);
    }

    public function classify(ClassifyRequest $request): AiClassificationResult {
        $system = implode("\n", [
            'Du ordnest einen Text Katalogwerten zu (Sprache: ' . $request->language . ').',
            'Erlaubte Werte (NUR diese, exakt wie geschrieben): ' . json_encode($request->catalog, JSON_UNESCAPED_UNICODE),
            $request->multiple
                ? 'Antworte NUR mit einem JSON-Array der passenden Werte (auch leer möglich).'
                : 'Antworte NUR mit einem JSON-Array mit höchstens EINEM Wert.',
        ]);

        $completion = $this->complete($system, $request->text, expectJson: true);

        return new AiClassificationResult($this->parseJsonStringList($completion->text), $completion->usage);
    }

    public function explain(ExplainRequest $request): AiTextResult {
        $system = implode("\n", [
            'Du erklärst Kennzahlen/Fehlercodes verständlich und gibst eine kurze, konkrete Handlungsempfehlung (Sprache: ' . $request->language . ').',
            'VERBINDLICH: Stütze dich nur auf die übergebenen Werte; keine erfundenen Ursachen als Fakten darstellen (Hypothesen als solche kennzeichnen).',
            'Antworte NUR mit der Erklärung.',
        ]);

        $user = (string) json_encode($request->facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($request->question !== null) {
            $user .= "\n\nFrage: " . $request->question;
        }

        $completion = $this->complete($system, $user);

        return new AiTextResult(trim($completion->text), $completion->usage);
    }

    public function find(FindRequest $request): AiFindResult {
        $system = implode("\n", [
            'Du wählst aus einem Korpus die relevantesten Einträge für eine Frage aus (Sprache: ' . $request->language . ').',
            sprintf('Antworte NUR mit einem JSON-Array der höchstens %d relevantesten Referenz-Keys, absteigend nach Relevanz.', max(1, $request->maxResults)),
        ]);

        $corpus = '';
        foreach ($request->corpus as $key => $text) {
            $corpus .= sprintf("[%s] %s\n", $key, $text);
        }

        $completion = $this->complete($system, 'Frage: ' . $request->query . "\n\nKorpus:\n" . $corpus, expectJson: true);

        $matches = array_values(array_intersect(
            $this->parseJsonStringList($completion->text),
            array_map('strval', array_keys($request->corpus))
        ));

        return new AiFindResult($matches, $completion->usage);
    }

    public function extract(ExtractRequest $request): AiExtractionResult {
        $schemaLines = [];
        foreach ($request->schema as $field => $description) {
            $schemaLines[] = sprintf('- "%s": %s', $field, $description);
        }

        $system = implode("\n", [
            'Du extrahierst Feldwerte aus einem Beleg-/Rechnungstext (Sprache: ' . $request->language . ').',
            'VERBINDLICH: Nur Werte verwenden, die wörtlich oder eindeutig ableitbar im Text stehen. Nichts erfinden, nichts schätzen.',
            'Zielfelder:',
            ...$schemaLines,
            'Antworte NUR mit einem JSON-Objekt: je Zielfeld ein Objekt {"value": <string|null>, "confidence": <0-100>}.',
            'Nicht gefundene Felder: {"value": null, "confidence": 0}.',
        ]);

        $completion = $this->complete($system, $request->text, expectJson: true);

        $values = [];
        $confidence = [];
        $decoded = $this->parseJsonObject($completion->text);
        foreach (array_keys($request->schema) as $field) {
            $entry = $decoded[$field] ?? null;
            $value = is_array($entry) ? ($entry['value'] ?? null) : (is_scalar($entry) ? $entry : null);
            $values[$field] = is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
            $confidence[$field] = is_array($entry) && is_numeric($entry['confidence'] ?? null)
                ? max(0, min(100, (int) $entry['confidence']))
                : ($values[$field] !== null ? 50 : 0);
        }

        return new AiExtractionResult($values, $confidence, $completion->usage);
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $glossary = array_filter(
            $request->glossary,
            static fn (GlossaryEntry $e): bool => $e->translation !== null
        );

        $system = implode("\n", array_filter([
            sprintf('Du übersetzt einen Text nach %s.', strtoupper($request->targetLanguage)),
            $request->sourceLanguage !== null ? 'Ausgangssprache: ' . strtoupper($request->sourceLanguage) . '.' : null,
            $request->format === 'html' ? 'Der Text ist HTML — Struktur und Tags unverändert erhalten.' : null,
            $request->formality === 'more' ? 'Formell übersetzen (Sie-Form, geschäftlich).' : null,
            $request->formality === 'less' ? 'Informell übersetzen.' : null,
            $glossary !== []
                ? 'VERBINDLICHE Terminologie: ' . implode('; ', array_map(
                    static fn (GlossaryEntry $e): string => sprintf('"%s" → "%s"', $e->term, $e->translation),
                    $glossary
                ))
                : null,
            'Antworte NUR mit der Übersetzung.',
        ]));

        $completion = $this->complete($system, $request->text);

        // LLM-Übersetzung: Terminologie per Prompt = probabilistisch
        // (Feature 025 — die UI kennzeichnet das entsprechend).
        return new AiTranslationResult(
            text: trim($completion->text),
            deterministicTerminology: false,
            detectedSourceLanguage: null,
            usage: $completion->usage,
        );
    }

    /** @param list<string> $rules */
    private function styleBlock(array $rules): ?string {
        return $rules === [] ? null : 'Stilregeln: ' . implode('; ', $rules);
    }

    /** @param list<GlossaryEntry> $glossary */
    private function glossaryBlock(array $glossary): ?string {
        if ($glossary === []) {
            return null;
        }

        return 'Glossar (Begriffe korrekt deuten/verwenden): ' . implode('; ', array_map(
            static fn (GlossaryEntry $e): string => sprintf('"%s" = %s', $e->term, $e->meaning),
            $glossary
        ));
    }

    /** @param list<ExamplePair> $examples */
    private function exampleBlock(array $examples): ?string {
        if ($examples === []) {
            return null;
        }

        return "Beispiele (Rohtext → gewünschtes Ergebnis):\n" . implode("\n", array_map(
            static fn (ExamplePair $e): string => sprintf('„%s" → „%s"', $e->source, $e->target),
            $examples
        ));
    }

    /**
     * Toleranter JSON-Objekt-Parser (Pendant zu {@see parseJsonStringList}):
     * akzeptiert auch in Code-Fences oder Text eingebettete Objekte.
     *
     * @return array<string, mixed>
     */
    protected function parseJsonObject(string $raw): array {
        $candidate = trim($raw);

        if (! str_starts_with($candidate, '{')) {
            $start = strpos($candidate, '{');
            $end = strrpos($candidate, '}');
            if ($start === false || $end === false || $end <= $start) {
                return [];
            }
            $candidate = substr($candidate, $start, $end - $start + 1);
        }

        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Toleranter JSON-Array-Parser: akzeptiert auch in Code-Fences oder
     * Text eingebettete Arrays — liefert IMMER eine String-Liste.
     *
     * @return list<string>
     */
    protected function parseJsonStringList(string $raw): array {
        $candidate = trim($raw);

        if (! str_starts_with($candidate, '[')) {
            $start = strpos($candidate, '[');
            $end = strrpos($candidate, ']');
            if ($start === false || $end === false || $end <= $start) {
                return [];
            }
            $candidate = substr($candidate, $start, $end - $start + 1);
        }

        $decoded = json_decode($candidate, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $decoded),
            static fn (string $v): bool => $v !== ''
        ));
    }
}
