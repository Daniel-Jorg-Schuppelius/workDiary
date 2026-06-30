<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Model;

/**
 * Ergebnis eines {@see EntityMatcher}-Laufs: gerankte lokale Kandidaten zu einem
 * Remote-Datensatz (höchste Confidence zuerst).
 */
final class MatchResult {
    /** @var array<string, int> */
    private const RANK = [
        MatchStrategy::FUZZY => 1,
        MatchStrategy::LIKELY => 2,
        MatchStrategy::EXACT => 3,
    ];

    /**
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     */
    public function __construct(private readonly array $candidates) {}

    /**
     * @return list<array{model: Model, confidence: string, reasons: list<string>}>
     */
    public function candidates(): array {
        return $this->candidates;
    }

    public function isEmpty(): bool {
        return $this->candidates === [];
    }

    /**
     * Bester Kandidat (höchste Confidence) oder null.
     *
     * @return array{model: Model, confidence: string, reasons: list<string>}|null
     */
    public function best(): ?array {
        return $this->candidates[0] ?? null;
    }

    /**
     * Genau ein Kandidat mit Confidence „exact" → für sichere Auto-Zuordnung.
     * Liefert null bei keinem, mehreren oder nur unsicheren Kandidaten.
     */
    public function uniqueExact(): ?Model {
        if (count($this->candidates) !== 1) {
            return null;
        }
        $only = $this->candidates[0];

        return $only['confidence'] === MatchStrategy::EXACT ? $only['model'] : null;
    }

    /**
     * Mehrere Kandidaten ODER ein einzelner, aber nicht eindeutiger (likely/fuzzy)
     * Kandidat — in beiden Fällen entscheidet ein Mensch.
     */
    public function needsHuman(): bool {
        return ! $this->isEmpty() && $this->uniqueExact() === null;
    }

    /**
     * Baut ein geranktes Ergebnis aus rohen Treffern (dedupliziert nach Modell,
     * höchste Confidence gewinnt, Gründe gesammelt).
     *
     * @param  array<int, array{model: Model, confidence: string, reason: string}>  $hits
     */
    public static function fromHits(array $hits): self {
        /** @var array<int|string, array{model: Model, confidence: string, reasons: array<string, true>}> $byId */
        $byId = [];
        foreach ($hits as $hit) {
            $key = $hit['model']->getKey();
            if (! isset($byId[$key])) {
                $byId[$key] = ['model' => $hit['model'], 'confidence' => $hit['confidence'], 'reasons' => []];
            }
            if (self::RANK[$hit['confidence']] > self::RANK[$byId[$key]['confidence']]) {
                $byId[$key]['confidence'] = $hit['confidence'];
            }
            $byId[$key]['reasons'][$hit['reason']] = true;
        }

        $ranked = array_map(static fn(array $c): array => [
            'model' => $c['model'],
            'confidence' => $c['confidence'],
            'reasons' => array_keys($c['reasons']),
        ], array_values($byId));

        usort($ranked, static fn(array $a, array $b): int => self::RANK[$b['confidence']] <=> self::RANK[$a['confidence']]);

        return new self($ranked);
    }
}
