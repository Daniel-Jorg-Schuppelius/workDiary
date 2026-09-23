<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchVocabulary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Search\{SearchDocument, SearchTerm};

/**
 * Wortverzeichnis des Suchindex je Organisation (Feature 153, MVP-772) —
 * Grundlage der Tippfehler-Toleranz: Ein unbekanntes Suchwort wird gegen die
 * tatsächlich indizierten Wörter abgeglichen („exchnage" → „exchange"), und
 * umgekehrt findet „exchange" die Notiz mit „exchage".
 *
 * Scoped gebunden: bereits geschriebene Wörter merkt sich die Instanz, ein
 * Neuaufbau schreibt jedes Wort nur einmal.
 */
final class SearchVocabulary {
    private const MIN_LENGTH = 3;

    private const MEMO_LIMIT = 50000;

    /** Kandidaten-Obergrenze je Ähnlichkeitssuche (gleicher Anfangsbuchstabe, ähnliche Länge). */
    private const CANDIDATE_LIMIT = 5000;

    /** @var array<int, array<string, true>> */
    private array $known = [];

    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /** @param  list<string>  $tokens */
    public function record(int $organizationId, array $tokens): void {
        $rows = [];
        foreach ($tokens as $token) {
            if (strlen($token) < self::MIN_LENGTH || isset($this->known[$organizationId][$token]) || preg_match('/[a-z]/', $token) !== 1) {
                continue;
            }
            $this->known[$organizationId][$token] = true;
            $rows[] = ['organization_id' => $organizationId, 'term' => $token];
        }

        if (count($this->known[$organizationId] ?? []) > self::MEMO_LIMIT) {
            $this->known[$organizationId] = [];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SearchTerm::query()->withoutGlobalScopes()->insertOrIgnore($chunk);
        }
    }

    /** Gibt es ein indiziertes Wort, das mit dem Suchwort beginnt? */
    public function knows(int $organizationId, string $token): bool {
        return SearchTerm::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereLikeEscaped('term', $token, 'prefix')
            ->exists();
    }

    /**
     * Ähnlich geschriebene indizierte Wörter, nächste zuerst. Toleranz: ein
     * Zeichen bis fünf Buchstaben, sonst zwei; ein vertauschtes Nachbarpaar
     * („smpt") zählt als ein Fehler.
     *
     * @return list<string>
     */
    public function similar(int $organizationId, string $token, int $max): array {
        $length = strlen($token);
        $tolerance = $length <= 5 ? 1 : 2;

        $candidates = SearchTerm::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereLikeEscaped('term', $token[0], 'prefix')
            ->whereRaw('LENGTH(term) BETWEEN ? AND ?', [$length - $tolerance, $length + $tolerance])
            ->where('term', '<>', $token)
            ->limit(self::CANDIDATE_LIMIT)
            ->pluck('term');

        $scored = [];
        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            $distance = self::distance($token, $candidate);
            if ($distance <= $tolerance) {
                $scored[] = [$candidate, $distance, strspn($token ^ substr($candidate, 0, $length), "\0")];
            }
        }

        usort($scored, static fn(array $a, array $b): int => [$a[1], -$a[2], $a[0]] <=> [$b[1], -$b[2], $b[0]]);

        return array_slice(array_column($scored, 0), 0, $max);
    }

    /** Wortverzeichnis einer Organisation aus dem Index neu aufbauen. */
    public function rebuild(int $organizationId): int {
        SearchTerm::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->delete();
        $this->known[$organizationId] = [];

        SearchDocument::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->select(['id', 'search_text'])
            ->chunkById(500, function ($documents) use ($organizationId): void {
                foreach ($documents as $document) {
                    $this->record($organizationId, $this->normalizer->decode((string) $document->search_text));
                }
            });

        return SearchTerm::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->count();
    }

    /** Levenshtein mit Vertauschung benachbarter Zeichen als einem Fehler. */
    private static function distance(string $a, string $b): int {
        $distance = levenshtein($a, $b);
        if ($distance === 2 && strlen($a) === strlen($b)) {
            $diff = [];
            for ($i = 0, $n = strlen($a); $i < $n; $i++) {
                if ($a[$i] !== $b[$i]) {
                    $diff[] = $i;
                }
            }
            if (count($diff) === 2 && $diff[1] === $diff[0] + 1 && $a[$diff[0]] === $b[$diff[1]] && $a[$diff[1]] === $b[$diff[0]]) {
                return 1;
            }
        }

        return $distance;
    }
}
