<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAiSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\{KnowledgeArticle, Organization};
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiInvocationResult, AiTextResult, ExplainRequest, FormulateRequest};
use Illuminate\Support\Str;

/**
 * KI-Unterstützung für Autoren und Lernende (Feature 149, MVP-746).
 *
 * **Die harte Grenze:** Diese Klasse erzeugt Entwürfe und Erklärungen —
 * sie bewertet nichts und entscheidet nichts. Eine KI, die Lernergebnisse
 * bewertet oder über Zugang entscheidet, wäre nach Anhang III Nr. 3 der
 * EU-KI-VO ein Hochrisiko-System. Deshalb gibt es hier bewusst **keine**
 * Methode, die Punkte vergibt, bestehen lässt oder zuweist.
 *
 * Der Tutor antwortet ausschließlich aus dem **freigegebenen** Kursinhalt
 * und den ausdrücklich verlinkten Wissensartikeln. Was nicht im Kontext
 * steht, wird nicht erfunden — der Prompt verlangt die Fundstelle.
 */
class LearningAiSuggestionService {
    public const CAPABILITY_OUTLINE = 'learning.course_outline';

    public const CAPABILITY_QUESTIONS = 'learning.question_draft';

    public const CAPABILITY_TUTOR = 'learning.tutor';

    /** Maximale Zeichenzahl des Kurskontexts — Kosten und Prompt-Grenze. */
    private const CONTEXT_LIMIT = 12000;

    public function __construct(
        private readonly AiInvocationService $invocation,
    ) {}

    /**
     * Kursgliederung als **Entwurf** aus Stichworten und optionalen Quellen.
     *
     * @param  list<KnowledgeArticle>  $articles
     */
    public function draftOutline(
        Organization $organization,
        string $topic,
        array $articles = [],
        ?string $audience = null,
        ?int $connectionId = null,
    ): string {
        $sources = [];
        foreach ($articles as $article) {
            $sources[] = $article->title . ': ' . Str::limit((string) $article->solution, 800);
        }

        $request = new FormulateRequest(
            text: $topic,
            language: (string) config('app.locale', 'de'),
            styleRules: [
                'Gliederung mit Abschnitten und Lerneinheiten, keine Fließtext-Wand.',
                'Je Einheit ein Lernziel in einem Satz.',
                'Keine erfundenen Rechtsgrundlagen oder Normen nennen.',
            ],
            contextHints: array_values(array_filter([
                $audience !== null ? 'Zielgruppe: ' . $audience : null,
                ...array_map(static fn (string $s): string => Str::limit($s, 900), $sources),
            ])),
        );

        return $this->textOf($this->invocation->invoke($organization, self::CAPABILITY_OUTLINE, $request, $connectionId));
    }

    /**
     * Prüfungsfragen als **Entwurf** zum Inhalt einer Lerneinheit. Die
     * Auswahl und die Bewertung bleiben beim Menschen.
     */
    public function draftQuestions(LearningUnit $unit, int $count = 5, ?int $connectionId = null): string {
        $organization = $unit->organization;

        if ($organization === null) {
            return '';
        }

        $request = new FormulateRequest(
            text: $this->unitText($unit),
            language: (string) config('app.locale', 'de'),
            styleRules: [
                'Genau ' . max(1, min($count, 20)) . ' Fragen vorschlagen.',
                'Je Frage: Fragetext, Antwortoptionen, markierte richtige Antwort, kurze Begründung.',
                'Nur Inhalte verwenden, die im Text vorkommen — nichts hinzuerfinden.',
            ],
            contextHints: ['Lerneinheit: ' . $unit->title],
        );

        return $this->textOf($this->invocation->invoke($organization, self::CAPABILITY_QUESTIONS, $request, $connectionId));
    }

    /**
     * Lerntutor: beantwortet eine Frage **im Kurskontext**. Der Kontext ist
     * die freigegebene Kursversion — nicht der Entwurf, an dem gerade
     * gearbeitet wird.
     */
    public function answerLearnerQuestion(LearningCourse $course, string $question, ?int $connectionId = null): string {
        $organization = $course->organization;

        if ($organization === null) {
            return '';
        }

        // ExplainRequest nimmt benannte Fakten (Schlüssel → Wert) plus die
        // Frage — der Kontext wird also benannt übergeben, nicht als Fließtext.
        $request = new ExplainRequest(
            facts: $this->courseContext($course),
            question: $question,
            language: (string) config('app.locale', 'de'),
        );

        return $this->textOf($this->invocation->invoke($organization, self::CAPABILITY_TUTOR, $request, $connectionId));
    }

    /**
     * Text aus dem Ergebnis ziehen. Andere Ergebnisarten (Klassifikation,
     * Extraktion) kommen bei diesen Verben nicht vor — sie liefern hier
     * leeren Text statt einer Ausnahme.
     */
    private function textOf(AiInvocationResult $result): string {
        return $result->result instanceof AiTextResult ? $result->result->text : '';
    }

    /**
     * Kontext des Tutors: die aktuelle **freigegebene** Version. Ein
     * Entwurf könnte falsche Zwischenstände enthalten.
     *
     * @return array<string, scalar|null>
     */
    private function courseContext(LearningCourse $course): array {
        $version = $course->currentVersion();
        $snapshot = $version?->snapshot() ?? [];
        $facts = [];

        $facts['kurs'] = (string) ($snapshot['course']['title'] ?? $course->title);

        if (! empty($snapshot['course']['objectives'])) {
            $facts['lernziele'] = (string) $snapshot['course']['objectives'];
        }

        $length = 0;
        foreach ($snapshot['units'] ?? [] as $index => $unit) {
            $text = $this->blocksToText($unit['blocks'] ?? []);
            if ($text === '') {
                continue;
            }

            $length += mb_strlen($text);

            if ($length > self::CONTEXT_LIMIT) {
                break;
            }

            // Der Schlüssel nennt die Fundstelle — der Tutor soll sie
            // zitieren können statt frei zu formulieren.
            $key = 'einheit_' . ($index + 1) . '_' . Str::slug((string) ($unit['title'] ?? ''));
            $facts[Str::limit($key, 60, '')] = $text;
        }

        return $facts;
    }

    private function unitText(LearningUnit $unit): string {
        return Str::limit($this->blocksToText($unit->blocks()), self::CONTEXT_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function blocksToText(array $blocks): string {
        $parts = [];

        foreach ($blocks as $block) {
            if (isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
            if (isset($block['items']) && is_array($block['items'])) {
                $parts[] = implode('; ', array_map(static fn (mixed $i): string => (string) $i, $block['items']));
            }
        }

        return trim(implode("\n", $parts));
    }
}
