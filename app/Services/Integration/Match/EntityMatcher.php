<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

/**
 * Generischer Entitäts-Abgleich: findet zu einem Remote-Datensatz die lokalen
 * Kandidaten gemäß den Strategien eines {@see MatchProfile}. Ersetzt die heute
 * pro Plugin duplizierte Match-Logik.
 *
 * Query-fähige Strategien (Exact/Composite) laufen als 1:n-DB-Suche; unscharfe
 * Strategien (Fuzzy) werden gegen die geladenen Kandidaten in-memory bewertet.
 */
class EntityMatcher {
    /** Obergrenze für In-Memory-Fuzzy-Vergleich pro Lauf (Schutz vor O(n²)). */
    private const FUZZY_CANDIDATE_CAP = 2000;

    /**
     * Findet lokale Kandidaten zu einem bereits ins lokale Schema gemappten
     * Wertesatz.
     *
     * @param  array<string, mixed>  $mapped  Wertesatz im lokalen Feldschema
     * @param  int|null  $excludeId  lokale ID, die nie Treffer sein darf (Self-Exclude)
     */
    public function match(Organization $organization, MatchProfile $profile, array $mapped, ?int $excludeId = null): MatchResult {
        $hits = [];
        $fuzzyStrategies = [];

        foreach ($profile->strategies() as $strategy) {
            $base = $profile->candidates($organization);
            if ($excludeId !== null) {
                $base->where($base->getModel()->getKeyName(), '!=', $excludeId);
            }

            $query = $strategy->query($base, $mapped);
            if ($query === null) {
                if ($strategy instanceof FuzzyField) {
                    $fuzzyStrategies[] = $strategy;
                }

                continue;
            }

            foreach ($query->limit(50)->get() as $model) {
                $hits[] = ['model' => $model, 'confidence' => $strategy->confidence, 'reason' => $strategy->reason];
            }
        }

        if ($fuzzyStrategies !== []) {
            $this->appendFuzzyHits($organization, $profile, $mapped, $excludeId, $fuzzyStrategies, $hits);
        }

        return MatchResult::fromHits($hits);
    }

    /**
     * Vergleicht zwei bereits gemappte Wertesätze paarweise (für den
     * Dubletten-Finder) und liefert die höchste Confidence + Gründe.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array{confidence: string|null, reasons: list<string>}
     */
    public function compare(MatchProfile $profile, array $a, array $b): array {
        $confidence = null;
        $reasons = [];
        $rank = [MatchStrategy::FUZZY => 1, MatchStrategy::LIKELY => 2, MatchStrategy::EXACT => 3];

        foreach ($profile->strategies() as $strategy) {
            if (! $strategy->matches($a, $b)) {
                continue;
            }
            $reasons[] = $strategy->reason;
            if ($confidence === null || $rank[$strategy->confidence] > $rank[$confidence]) {
                $confidence = $strategy->confidence;
            }
        }

        return ['confidence' => $confidence, 'reasons' => array_values(array_unique($reasons))];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  list<FuzzyField>  $fuzzyStrategies
     * @param  array<int, array{model: Model, confidence: string, reason: string}>  $hits
     */
    private function appendFuzzyHits(
        Organization $organization,
        MatchProfile $profile,
        array $mapped,
        ?int $excludeId,
        array $fuzzyStrategies,
        array &$hits,
    ): void {
        $base = $profile->candidates($organization);
        if ($excludeId !== null) {
            $base->where($base->getModel()->getKeyName(), '!=', $excludeId);
        }

        foreach ($base->limit(self::FUZZY_CANDIDATE_CAP)->get() as $model) {
            $candidateFields = $profile->extract($model);
            foreach ($fuzzyStrategies as $strategy) {
                if ($strategy->matches($mapped, $candidateFields)) {
                    $hits[] = ['model' => $model, 'confidence' => $strategy->confidence, 'reason' => $strategy->reason];
                }
            }
        }
    }
}
