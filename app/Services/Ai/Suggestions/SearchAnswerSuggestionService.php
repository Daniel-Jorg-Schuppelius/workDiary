<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchAnswerSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\Platform\{Organization, User};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiTextResult, SummarizeRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchHit, ActivitySearchService};
use CommonToolkit\Helper\Data\StringHelper;

/**
 * KI-Antwort der Tätigkeitsrecherche (Feature 153, MVP-775): fasst die
 * Treffer zu „was wurde wann bei welchem Kunden gemacht" zusammen.
 *
 * Quelle sind AUSSCHLIESSLICH die rechtegeprüften Treffer des Suchenden
 * ({@see ActivitySearchService::topHits()}). Kundenbezüge gehen nur als
 * Kennung („Kunde A") in den Prompt und werden in der Antwort zurückübersetzt;
 * Kundennamen im Freitext maskiert der {@see CustomerNameMasker}. Die
 * Capability ist `high` (lokal-exklusiv). Die Antwort ist eine Einsicht am
 * Nutzer — sie schreibt nirgendwohin und kann nur verworfen werden.
 */
class SearchAnswerSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'search.answer_summarize';

    /** @var list<string> */
    private const RULES = [
        'Beantworte ausschließlich aus den aufgeführten Einträgen, was wann bei welchem Kunden gemacht wurde — nichts ergänzen, nichts bewerten.',
        'Nenne je Vorgang das Datum und die Kundenkennung genau so, wie sie in den Einträgen stehen (z. B. „Kunde A").',
        'Fasse zusammengehörige Einträge zu einem Vorgang zusammen; höchstens acht Sätze.',
        'Keine Personennamen nennen.',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly CustomerNameMasker $masker,
        private readonly ActivitySearchService $search,
    ) {}

    public function answer(User $user, Organization $organization, ActivitySearchCriteria $criteria, ?int $connectionId = null): AiTextSuggestion {
        $hits = $this->search->topHits($user, $criteria, (int) config('search.answer_max_hits', 40));
        if ($hits === []) {
            throw new AiException((string) __('search.ai.no_hits'));
        }

        // Chronologisch: die Antwort soll vorwärts erzählen.
        usort($hits, static fn(ActivitySearchHit $a, ActivitySearchHit $b): int => ($a->occurredAt?->getTimestamp() ?? 0) <=> ($b->occurredAt?->getTimestamp() ?? 0));

        /** @var array<string, string> $aliases Kundenbezug → Kennung */
        $aliases = [];
        $items = [];
        foreach ($hits as $hit) {
            $label = $hit->customerLabel();
            if ($label !== null && ! isset($aliases[$label])) {
                $aliases[$label] = (string) __('search.ai.customer_alias', ['letter' => self::letter(count($aliases))]);
            }

            $text = $hit->showsTitle() ? self::joinText($hit->title, $hit->excerpt) : (string) $hit->excerpt;
            $items[] = implode(' · ', array_filter([
                $hit->dateLabel(),
                $hit->type->label(),
                $label !== null ? $aliases[$label] : null,
                $hit->projectName !== null ? $this->masker->mask($organization, $hit->projectName) : null,
                $hit->durationLabel(),
                StringHelper::truncate($this->masker->mask($organization, StringHelper::normalizeWhitespace($text)), 300, '…'),
            ], static fn(?string $v): bool => $v !== null && $v !== ''));
        }

        $request = new SummarizeRequest(
            items: $items,
            language: app()->getLocale(),
            styleRules: array_merge(self::RULES, $this->memory->styleRulesFor($organization, self::CAPABILITY)),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal(
            (int) $organization->id,
            $user,
            self::CAPABILITY,
            (string) __('search.ai.source_hint', ['query' => $criteria->query !== '' ? $criteria->query : '—', 'count' => count($hits)]),
            strtr($payload->text, array_flip($aliases)),
            $result,
            $user,
        );
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function letter(int $index): string {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)) . $letters;
        }

        return $letters;
    }

    private static function joinText(string $title, ?string $excerpt): string {
        return $excerpt !== null && trim($excerpt) !== '' ? $title . ': ' . $excerpt : $title;
    }
}
