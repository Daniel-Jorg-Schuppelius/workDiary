<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchQueryParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Query;

use App\Services\Search\{SearchSynonyms, SearchTextNormalizer, SearchVocabulary};

/**
 * Suchanfrage → {@see ParsedSearchQuery} (Feature 153, MVP-771/772).
 *
 * - Wörter sind UND-verknüpft, gesucht wird am Wortanfang.
 * - „Phrasen" in Anführungszeichen verlangen benachbarte Wörter.
 * - `-wort` schließt aus.
 * - Füllwörter („wann haben wir … gemacht") fallen, solange ein Inhaltswort
 *   bleibt.
 * - Synonymgruppen der Organisation gelten auch für Mehrwortbegriffe
 *   („send connector").
 * - Ein Wort, das im Index nicht vorkommt, wird durch ähnlich geschriebene
 *   indizierte Wörter ergänzt; mit `$similar` auch bekannte Wörter.
 */
final class SearchQueryParser {
    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchSynonyms $synonyms,
        private readonly SearchVocabulary $vocabulary,
    ) {}

    public function parse(string $query, int $organizationId, bool $similar = false): ParsedSearchQuery {
        $query = mb_substr(trim($query), 0, (int) config('search.max_query_length', 200));
        if ($query === '') {
            return new ParsedSearchQuery;
        }

        /** @var list<array{words: list<string>, phrase: bool}> $drafts */
        $drafts = [];
        /** @var list<SearchAlternative> $excluded */
        $excluded = [];

        $query = (string) preg_replace_callback('/"([^"]*)"|„([^“”"]*)[“”"]/u', function (array $m) use (&$drafts): string {
            $words = $this->words($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
            if ($words !== []) {
                $drafts[] = ['words' => $words, 'phrase' => true];
            }

            return ' ';
        }, $query);

        foreach (preg_split('/\s+/u', $query) ?: [] as $raw) {
            $negative = str_starts_with($raw, '-') && strlen($raw) > 1;
            foreach ($this->normalizer->chunks($negative ? substr($raw, 1) : $raw) as $parts) {
                if ($negative) {
                    array_push($excluded, ...SearchAlternative::forWords($parts));
                } else {
                    $drafts[] = ['words' => $parts, 'phrase' => false];
                }
            }
        }

        [$drafts, $ignored] = $this->dropStopwords($drafts);
        $map = $this->synonyms->mapFor($organizationId);
        $drafts = $this->mergeMultiwordSynonyms($drafts, $map);

        $groups = [];
        $corrections = [];
        $synonymsUsed = [];
        $minLength = (int) config('search.fuzzy.min_length', 4);
        $maxCandidates = (int) config('search.fuzzy.max_candidates', 3);

        foreach ($drafts as $draft) {
            $words = $draft['words'];
            $alternatives = SearchAlternative::forWords($words, SearchAlternative::ORIGIN_INPUT, $draft['phrase']);
            if ($alternatives === []) {
                continue;
            }

            $key = implode(' ', $words);
            foreach ($map[$key] ?? [] as $synonymWords) {
                array_push($alternatives, ...SearchAlternative::forWords($synonymWords, SearchAlternative::ORIGIN_SYNONYM));
                $synonymsUsed[$key][] = implode(' ', $synonymWords);
            }

            if (count($words) === 1 && strlen($words[0]) >= $minLength && preg_match('/[a-z]/', $words[0]) === 1) {
                $known = $this->vocabulary->knows($organizationId, $words[0]);
                if (! $known || $similar) {
                    $candidates = $this->vocabulary->similar($organizationId, $words[0], $maxCandidates);
                    foreach ($candidates as $candidate) {
                        $alternatives[] = new SearchAlternative(SearchAlternative::EXACT, [$candidate], SearchAlternative::ORIGIN_SIMILAR);
                    }
                    if (! $known && $candidates !== []) {
                        $corrections[$words[0]] = $candidates;
                    }
                }
            }

            $unique = [];
            foreach ($alternatives as $alternative) {
                $unique[$alternative->key()] ??= $alternative;
            }
            $groups[] = array_values($unique);
        }

        return new ParsedSearchQuery($groups, $excluded, $corrections, $synonymsUsed, $ignored);
    }

    /** @return list<string> */
    private function words(string $text): array {
        $words = [];
        foreach ($this->normalizer->chunks($text) as $parts) {
            foreach ($parts as $part) {
                $words[] = $part;
            }
        }

        return $words;
    }

    /**
     * @param  list<array{words: list<string>, phrase: bool}>  $drafts
     * @return array{0: list<array{words: list<string>, phrase: bool}>, 1: list<string>}
     */
    private function dropStopwords(array $drafts): array {
        $stopwords = array_flip((array) config('search.stopwords', []));
        $content = [];
        $ignored = [];
        foreach ($drafts as $draft) {
            if (! $draft['phrase'] && count($draft['words']) === 1 && isset($stopwords[$draft['words'][0]])) {
                $ignored[] = $draft['words'][0];
            } else {
                $content[] = $draft;
            }
        }

        return $content === [] ? [$drafts, []] : [$content, array_values(array_unique($ignored))];
    }

    /**
     * Aufeinanderfolgende Einzelwörter, die zusammen ein Synonym-Begriff sind
     * („send connector"), werden eine Gruppe.
     *
     * @param  list<array{words: list<string>, phrase: bool}>  $drafts
     * @param  array<string, list<list<string>>>  $map
     * @return list<array{words: list<string>, phrase: bool}>
     */
    private function mergeMultiwordSynonyms(array $drafts, array $map): array {
        $merged = [];
        $count = count($drafts);
        for ($i = 0; $i < $count;) {
            foreach ([3, 2] as $size) {
                $window = array_slice($drafts, $i, $size);
                if (count($window) !== $size) {
                    continue;
                }
                $single = array_filter($window, static fn(array $d): bool => ! $d['phrase'] && count($d['words']) === 1);
                if (count($single) !== $size) {
                    continue;
                }
                $key = implode(' ', array_map(static fn(array $d): string => $d['words'][0], $window));
                if (isset($map[$key])) {
                    $merged[] = ['words' => explode(' ', $key), 'phrase' => false];
                    $i += $size;

                    continue 2;
                }
            }
            $merged[] = $drafts[$i];
            $i++;
        }

        return $merged;
    }
}
