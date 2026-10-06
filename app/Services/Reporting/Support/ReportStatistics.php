<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportStatistics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Support;

/**
 * Statistik ohne Fachbezug für die Wertberichte (Konsolidierungs-Audit
 * 2026-10, k3-11): Kunden- und Lieferantenwert rechneten Konzentration und
 * Quintil-Punkte je für sich, wortgleich.
 */
final class ReportStatistics {
    /**
     * Konzentration einer Wertreihe: Summe, Anteil der größten fünf und zehn
     * Werte in Prozent und der Herfindahl-Index (0–10000). Nur positive Werte
     * zählen.
     *
     * @param  iterable<float>  $values
     * @return array{total: float, top5Share: float|null, top10Share: float|null, hhi: int|null, positive: int}
     */
    public static function concentration(iterable $values): array {
        $positive = collect($values)->filter(static fn (float $v): bool => $v > 0)->sortDesc()->values();
        $total = (float) $positive->sum();
        $share = static fn (int $take): ?float => $total > 0 ? round((float) $positive->take($take)->sum() / $total * 100, 1) : null;

        return [
            'total' => round($total, 2),
            'top5Share' => $share(5),
            'top10Share' => $share(10),
            'hhi' => $total > 0
                ? (int) round($positive->reduce(static fn (float $carry, float $v): float => $carry + (($v / $total * 100) ** 2), 0.0))
                : null,
            'positive' => $positive->count(),
        ];
    }

    /**
     * Punkte 1–5 je Schlüssel nach der Lage des Werts in der Reihe (Quintile,
     * RFM-Bewertung); bei `$higherIsBetter = false` gedreht.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, float|int>  $values
     * @return array<TKey, int>
     */
    public static function quintileScores(array $values, bool $higherIsBetter): array {
        $n = count($values);
        if ($n === 0) {
            return [];
        }

        $sorted = array_values($values);
        sort($sorted);
        $scores = [];
        foreach ($values as $key => $value) {
            $below = 0;
            foreach ($sorted as $v) {
                if ($v < $value) {
                    $below++;
                } else {
                    break;
                }
            }
            $score = min(5, (int) floor($below / $n * 5) + 1);
            $scores[$key] = $higherIsBetter ? $score : 6 - $score;
        }

        return $scores;
    }

    /**
     * Segment aus den RFM-Punkten. Die Schwellen gelten für Kunden und
     * Lieferanten gleich, die Namen nennt der Report.
     *
     * @param  array{inactive: string, new: string, top: string, lapsed: string, regular: string, occasional: string}  $names
     */
    public static function rfmSegment(bool $active, bool $isNew, ?int $r, ?int $f, ?int $m, array $names): string {
        if (! $active) {
            return $names['inactive'];
        }
        if ($isNew) {
            return $names['new'];
        }
        if (($r ?? 0) >= 4 && ($f ?? 0) >= 4 && ($m ?? 0) >= 4) {
            return $names['top'];
        }
        if (($r ?? 0) <= 2 && ($m ?? 0) >= 4) {
            return $names['lapsed'];
        }
        if (($r ?? 0) <= 2) {
            return $names['inactive'];
        }
        if (($f ?? 0) >= 3) {
            return $names['regular'];
        }

        return $names['occasional'];
    }
}
