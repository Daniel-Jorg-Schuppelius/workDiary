<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSearch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use App\Models\Platform\{HelpTopic, User};
use App\Services\Search\{SearchTextNormalizer, SearchVocabulary};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{App, Cache};

/**
 * Suche der Hilfe (MVP-1079) für Hilfecenter, Drawer und Befehlspalette:
 * Wortsuche über `help_topics.search_text`, gewichtet nach Feld und Seltenheit,
 * mit Tippfehler-Toleranz. Dieselbe Rangfolge gilt für Seiten und Aktionen der
 * Palette ({@see rank()}). Sichtbarkeit bleibt beim {@see HelpTopicResolver}.
 *
 * @phpstan-type SearchField array{weight: float, texts?: list<string>, tokens?: list<string>}
 */
final class HelpSearch {
    public const WEIGHT_TITLE = 5.0;
    public const WEIGHT_KEYWORDS = 4.0;
    public const WEIGHT_HEADINGS = 2.0;
    public const WEIGHT_BODY = 1.0;

    private const LEVEL_EXACT = 1.0;
    private const LEVEL_PREFIX = 0.8;
    private const LEVEL_INFIX = 0.5;

    /** Wortteil-Treffer (Komposita) erst ab dieser Länge, sonst trifft „an" fast alles. */
    private const INFIX_MIN_LENGTH = 4;

    private const TYPO_MIN_LENGTH = 4;
    private const MAX_CORRECTIONS = 3;
    private const CORRECTION_PENALTY = 0.9;
    private const MAX_WORDS = 8;

    /** Ein Wort in mehr als diesem Anteil der Themen (mindestens COMMON_MIN) ist ein Füllwort und kein Muss. */
    private const COMMON_SHARE = 0.4;
    private const COMMON_MIN = 5;

    /** Zuschlag (Anteil des Feldgewichts), wenn die Anfrage genau einem Titel oder Suchbegriff entspricht. */
    private const EXACT_BONUS = 0.2;

    /** Abschlag je Position eines Texts im Feld (höchstens zehnfach). */
    private const POSITION_DECAY = 0.02;

    /** Teiltreffer brauchen diesen Anteil der besten Wertung, sonst fallen sie weg. */
    private const PARTIAL_CUTOFF = 0.5;

    private const SNIPPET_BEFORE = 60;
    private const SNIPPET_AFTER = 120;
    private const SNIPPET_LEAD = 160;

    public function __construct(
        private readonly HelpTopicResolver $resolver,
        private readonly SearchTextNormalizer $normalizer,
    ) {}

    public function prepare(string $query, ?string $preferredLocale = null): HelpSearchQuery {
        $locale = $preferredLocale ?? App::getLocale();
        $raw = trim($query);

        $parts = [];
        foreach ($this->normalizer->chunks($raw) as $chunk) {
            foreach ($chunk as $part) {
                $parts[] = $part;
            }
        }
        // Füllwörter fallen, solange ein Inhaltswort bleibt („wie schalte ich den Darkmode ein").
        $stopwords = array_flip([...(array) config('search.stopwords', []), ...(array) config('help-center.stopwords.' . $locale, [])]);
        $content = array_values(array_filter($parts, static fn(string $part): bool => ! isset($stopwords[$part])));
        if ($content !== []) {
            $parts = $content;
        }
        $parts = array_slice($parts, 0, self::MAX_WORDS);
        $folded = array_values(array_unique(array_filter($parts, static fn(string $part): bool => strlen($part) >= 2)));
        // „Dark Mode" = „Darkmode": nur für kurze Folgen, sonst entsteht Wortsalat.
        $joined = count($parts) >= 2 && count($parts) <= 3 ? implode('', $parts) : null;
        if ($joined !== null && (strlen($joined) < 3 || strlen($joined) > SearchTextNormalizer::MAX_TOKEN_LENGTH)) {
            $joined = null;
        }

        $total = max(1, HelpTopic::query()->where('locale', $locale)->count());
        $common = max(self::COMMON_MIN, (int) ceil($total * self::COMMON_SHARE));

        $terms = [];
        $corrections = [];
        foreach ($folded as $word) {
            $forms = [$word];
            $penalty = 1.0;
            $count = $this->documentCount($locale, $forms);
            if ($count === 0 && strlen($word) >= self::TYPO_MIN_LENGTH && preg_match('/[a-z]/', $word) === 1) {
                $forms = SearchVocabulary::closest($word, $this->vocabulary($locale), self::MAX_CORRECTIONS);
                $count = $forms === [] ? 0 : $this->documentCount($locale, $forms);
                $penalty = self::CORRECTION_PENALTY;
                if ($count > 0) {
                    $corrections[$word] = $forms[0];
                }
            }
            // Unbekannte Wörter bleiben Pflicht: Seiten und Aktionen können sie
            // tragen, Hilfethemen enthalten sie nicht (dann Teiltreffer).
            $terms[] = $count === 0
                ? ['word' => $word, 'forms' => [$word], 'weight' => log(1 + $total), 'required' => true, 'known' => false]
                : ['word' => $word, 'forms' => $forms, 'weight' => log(1 + $total / $count) * $penalty, 'required' => $count <= $common, 'known' => true];
        }
        // Nur Füllwörter getroffen: dann zählen sie alle.
        if ($terms !== [] && ! in_array(true, array_column($terms, 'required'), true)) {
            $terms = array_map(static fn(array $term): array => ['required' => true] + $term, $terms);
        }

        $joinedWeight = 0.0;
        if ($joined !== null && ! in_array($joined, $folded, true)) {
            $count = $this->documentCount($locale, [$joined]);
            $joinedWeight = $count > 0 ? log(1 + $total / $count) : 0.0;
        }
        if ($joinedWeight === 0.0) {
            $joined = null;
        }

        $highlights = [];
        foreach (preg_split('/[\s,;:!?()"„“”»«]+/u', $raw) ?: [] as $term) {
            if (mb_strlen($term) >= 2) {
                $highlights[] = $term;
            }
        }
        foreach ($terms as $term) {
            if ($term['known']) {
                array_push($highlights, ...$term['forms']);
            }
        }
        $highlights = array_values(array_unique($highlights));
        usort($highlights, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return new HelpSearchQuery($raw, $locale, implode(' ', $parts), array_map('strval', $folded), $terms, $joined, $joinedWeight, $corrections, $highlights);
    }

    /** Alle für den Nutzer sichtbaren Themen der Anfragesprache, beste zuerst, ohne Langtext. */
    public function search(HelpSearchQuery $query, ?User $user): HelpSearchResult {
        $forms = $query->candidateForms();
        if ($forms === []) {
            return new HelpSearchResult($query, collect(), false);
        }

        $rows = HelpTopic::query()
            ->where('locale', $query->locale)
            ->where(fn(Builder $where) => $this->orWhereForms($where, $forms))
            ->orderBy('title')
            ->get(['id', 'topic', 'locale', 'title', 'keywords', 'headings', 'audience', 'modules', 'version', 'search_text'])
            ->filter(fn(HelpTopic $row): bool => $this->resolver->isVisibleFor($row, $user));

        $ranked = $this->rank($query, $rows, fn(HelpTopic $row): array => [
            'title' => ['weight' => self::WEIGHT_TITLE, 'texts' => [$row->title]],
            'keywords' => ['weight' => self::WEIGHT_KEYWORDS, 'texts' => array_map('strval', $row->keywords ?? [])],
            'headings' => ['weight' => self::WEIGHT_HEADINGS, 'texts' => array_values(array_map(static fn(array $heading): string => (string) $heading['text'], $row->headings ?? []))],
            'body' => ['weight' => self::WEIGHT_BODY, 'tokens' => $this->normalizer->decode((string) $row->search_text)],
        ]);

        $topics = collect($ranked['hits'])->map(static function (array $hit): HelpTopic {
            $row = $hit['entry'];
            $row->setAttribute('search_keyword', $hit['keyword']);
            $row->offsetUnset('search_text');

            return $row;
        })->values();

        return new HelpSearchResult($query, $topics, $ranked['partial']);
    }

    /**
     * Bewertet beliebige Einträge nach den Regeln der Hilfesuche: zuerst
     * Treffer mit allen Pflichtwörtern, danach starke Teiltreffer (ab
     * PARTIAL_CUTOFF der besten Wertung) — „Urlaub eintragen" zeigt so auch
     * die Abwesenheiten, deren Text „eintragen" nicht enthält.
     *
     * @template T
     * @param  iterable<T>  $entries
     * @param  \Closure(T): array<string, SearchField>  $fields  Feldname → Gewicht und Texte oder fertige Suchwörter
     * @return array{hits: list<array{entry: T, score: float, keyword: string|null, complete: bool}>, partial: bool}
     */
    public function rank(HelpSearchQuery $query, iterable $entries, \Closure $fields): array {
        if ($query->isEmpty()) {
            return ['hits' => [], 'partial' => false];
        }

        $complete = [];
        $partial = [];
        foreach ($entries as $entry) {
            $match = $this->match($query, $fields($entry));
            if ($match === null) {
                continue;
            }
            $hit = ['entry' => $entry, 'score' => $match['score'], 'keyword' => $match['keyword'], 'complete' => $match['complete']];
            if ($match['complete']) {
                $complete[] = $hit;
            } elseif ($match['required'] > 0) {
                $partial[] = $hit;
            }
        }

        $byScore = static fn(array $a, array $b): int => $b['score'] <=> $a['score'];
        usort($complete, $byScore);
        usort($partial, $byScore);
        $top = $complete[0]['score'] ?? $partial[0]['score'] ?? 0.0;
        $partial = array_values(array_filter($partial, static fn(array $hit): bool => $hit['score'] >= $top * self::PARTIAL_CUTOFF));

        return ['hits' => [...$complete, ...$partial], 'partial' => $complete === [] && $partial !== []];
    }

    /**
     * Eine Seite der Treffer mit Textausschnitt (`search_snippet`); den Langtext
     * lädt nur die angezeigte Seite nach.
     *
     * @return LengthAwarePaginator<int, HelpTopic>
     */
    public function paginate(HelpSearchResult $result, int $perPage, int $page): LengthAwarePaginator {
        $items = $result->topics->forPage($page, $perPage)->values();
        if ($items->isNotEmpty()) {
            $bodies = HelpTopic::query()->whereIn('id', $items->pluck('id'))->pluck('body_md', 'id');
            foreach ($items as $row) {
                $row->setAttribute('search_snippet', $this->snippetFor((string) $bodies->get($row->id, ''), $result->query->highlights));
            }
        }

        return new LengthAwarePaginator($items, $result->count(), $perPage, $page);
    }

    /**
     * @param  array<string, SearchField>  $fields
     * @return array{score: float, complete: bool, required: int, keyword: string|null}|null
     */
    private function match(HelpSearchQuery $query, array $fields): ?array {
        $groups = [];
        foreach ($fields as $name => $field) {
            if (isset($field['tokens'])) {
                $groups[] = [$name, $field['weight'], null, $field['tokens']];
            }
            // Frühere Texte eines Felds zählen etwas mehr: die ersten Suchbegriffe sind die wichtigsten.
            foreach ($field['texts'] ?? [] as $position => $text) {
                $groups[] = [$name, $field['weight'] * (1 - self::POSITION_DECAY * min($position, 10)), $text, $this->normalizer->tokens($text)];
            }
        }

        $score = 0.0;
        $required = 0;
        $keyword = null;
        foreach ($query->terms as $term) {
            [$best, $field, $text] = $this->best($term['forms'], $groups);
            if ($best <= 0.0) {
                continue;
            }
            $score += $best * $term['weight'];
            if ($term['required']) {
                $required++;
            }
            if ($field === 'keywords') {
                $keyword ??= $text;
            }
        }
        $complete = $required === $query->requiredCount() && $score > 0.0;

        if ($query->joined !== null) {
            [$best, $field, $text] = $this->best([$query->joined], $groups);
            if ($best > 0.0) {
                // „Dark Mode" trifft „Darkmode": zählt wie alle Wörter.
                $score = max($score, $best * $query->joinedWeight * max(1, count($query->terms)));
                $complete = true;
                $required = max(1, $required);
                if ($field === 'keywords') {
                    $keyword ??= $text;
                }
            }
        }

        if ($score <= 0.0) {
            return null;
        }

        // Die ganze Anfrage ist genau ein Titel oder Suchbegriff („2FA"): entscheidet
        // Gleichstände, ohne einen Titeltreffer zu überholen.
        $strongest = max([$query->joinedWeight, ...array_column($query->terms, 'weight')]);
        foreach ($groups as [$field, $weight, $text]) {
            if ($text !== null && ($field === 'title' || $field === 'keywords') && $this->normalizer->key($text) === $query->phrase) {
                $score += $weight * $strongest * self::EXACT_BONUS;
                if ($field === 'keywords') {
                    $keyword ??= $text;
                }
                break;
            }
        }

        return ['score' => $score, 'complete' => $complete, 'required' => $required, 'keyword' => $keyword];
    }

    /**
     * Bester Treffer einer der Formen über alle Feldgruppen.
     *
     * @param  list<string>  $forms
     * @param  list<array{0: string, 1: float, 2: string|null, 3: list<string>}>  $groups
     * @return array{0: float, 1: string|null, 2: string|null}
     */
    private function best(array $forms, array $groups): array {
        $best = 0.0;
        $bestField = null;
        $bestText = null;
        foreach ($groups as [$field, $weight, $text, $tokens]) {
            foreach ($forms as $form) {
                $value = $weight * $this->level($form, $tokens);
                if ($value > $best) {
                    $best = $value;
                    $bestField = $field;
                    $bestText = $text;
                }
            }
        }

        return [$best, $bestField, $bestText];
    }

    /** @param list<string> $tokens */
    private function level(string $form, array $tokens): float {
        $level = 0.0;
        foreach ($tokens as $token) {
            if ($token === $form) {
                return self::LEVEL_EXACT;
            }
            if (str_starts_with($token, $form)) {
                $level = self::LEVEL_PREFIX;
            } elseif ($level < self::LEVEL_INFIX && strlen($form) >= self::INFIX_MIN_LENGTH && str_contains($token, $form)) {
                $level = self::LEVEL_INFIX;
            }
        }

        return $level;
    }

    /** @param list<string> $forms */
    private function documentCount(string $locale, array $forms): int {
        return HelpTopic::query()
            ->where('locale', $locale)
            ->where(fn(Builder $where) => $this->orWhereForms($where, $forms))
            ->count();
    }

    /**
     * Vorfilter auf den kodierten Suchtext: Wortanfang (`' w' . form`), ab
     * INFIX_MIN_LENGTH auch Wortteil. Die genaue Bewertung macht {@see level()}.
     *
     * @param  Builder<HelpTopic>  $where
     * @param  list<string>  $forms
     */
    private function orWhereForms(Builder $where, array $forms): void {
        foreach ($forms as $form) {
            $where->orWhereLikeEscaped('search_text', strlen($form) >= self::INFIX_MIN_LENGTH ? $form : ' ' . SearchTextNormalizer::TOKEN_PREFIX . $form);
        }
    }

    /**
     * Wörter aus Titeln, Suchbegriffen und Überschriften einer Sprache — die
     * Kandidaten für Tippfehler-Korrekturen. Zwischengespeichert bis zum
     * nächsten Reindex.
     *
     * @return list<string>
     */
    private function vocabulary(string $locale): array {
        $stamp = (string) HelpTopic::query()->where('locale', $locale)->max('updated_at') . '|' . HelpTopic::query()->where('locale', $locale)->count();

        return Cache::remember('help-search:vocabulary:' . $locale . ':' . md5($stamp), 86400, function () use ($locale): array {
            $words = [];
            foreach (HelpTopic::query()->where('locale', $locale)->get(['title', 'keywords', 'headings']) as $row) {
                $texts = [(string) $row->title, ...array_map('strval', $row->keywords ?? []), ...array_map(static fn(array $heading): string => (string) $heading['text'], $row->headings ?? [])];
                foreach ($texts as $text) {
                    foreach ($this->normalizer->tokens($text) as $token) {
                        if (strlen($token) >= 3 && preg_match('/[a-z]/', $token) === 1) {
                            $words[$token] = true;
                        }
                    }
                }
            }

            return array_map('strval', array_keys($words));
        });
    }

    /**
     * Ausschnitt um den ersten Treffer als [vor, Treffer, nach] — rohe
     * Segmente, die View escaped jedes einzeln und hebt nur den Treffer
     * hervor. Ohne Treffer im Text beginnt der Ausschnitt am Textanfang.
     *
     * @param  list<string>  $highlights
     * @return array{0:string, 1:string, 2:string}
     */
    private function snippetFor(string $bodyMd, array $highlights): array {
        $text = StringHelper::normalizeWhitespace($bodyMd, unicode: true);

        foreach ($highlights as $highlight) {
            $pos = mb_stripos($text, $highlight);
            if ($pos === false) {
                continue;
            }
            $length = mb_strlen($highlight);
            $start = max(0, $pos - self::SNIPPET_BEFORE);
            $pre = ($start > 0 ? '…' : '') . mb_substr($text, $start, $pos - $start);
            $hit = mb_substr($text, $pos, $length);
            $post = mb_substr($text, $pos + $length, self::SNIPPET_AFTER) . (mb_strlen($text) > $pos + $length + self::SNIPPET_AFTER ? '…' : '');

            return [$pre, $hit, $post];
        }

        return ['', '', mb_substr($text, 0, self::SNIPPET_LEAD) . (mb_strlen($text) > self::SNIPPET_LEAD ? '…' : '')];
    }
}
