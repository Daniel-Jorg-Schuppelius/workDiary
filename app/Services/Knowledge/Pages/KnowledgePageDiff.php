<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageDiff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Zeilenvergleich zweier Fassungen (Feature 191, MVP-1664): längste
 * gemeinsame Teilfolge über die Zeilen, gemeinsamer Anfang und gemeinsames
 * Ende vorab abgeschnitten. Ist der geänderte Mittelteil zu groß für die
 * Tabelle, erscheint er als ganz entfernt und ganz neu — lieber grob als
 * ein Speicherüberlauf.
 *
 * Kein Toolkit-Baustein vorhanden (sebastian/diff ist nur Entwicklungs-
 * abhängigkeit); Kandidat für den php-common-toolkit.
 */
final class KnowledgePageDiff {
    /** Höchstens so viele Zellen hat die Vergleichstabelle des Mittelteils. */
    private const int MAX_CELLS = 250_000;

    /** @return list<array{op: 'same'|'add'|'del', text: string}> */
    public function lines(?string $old, ?string $new): array {
        $a = StringHelper::lines(StringHelper::normalizeLineEndings((string) $old, "\n"));
        $b = StringHelper::lines(StringHelper::normalizeLineEndings((string) $new, "\n"));

        $prefix = 0;
        while ($prefix < count($a) && $prefix < count($b) && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < count($a) - $prefix && $suffix < count($b) - $prefix && $a[count($a) - 1 - $suffix] === $b[count($b) - 1 - $suffix]) {
            $suffix++;
        }

        $rows = [];
        for ($i = 0; $i < $prefix; $i++) {
            $rows[] = ['op' => 'same', 'text' => $a[$i]];
        }
        $middleA = array_slice($a, $prefix, count($a) - $prefix - $suffix);
        $middleB = array_slice($b, $prefix, count($b) - $prefix - $suffix);
        array_push($rows, ...$this->middle($middleA, $middleB));
        for ($i = count($a) - $suffix; $i < count($a); $i++) {
            $rows[] = ['op' => 'same', 'text' => $a[$i]];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{op: 'same'|'add'|'del', text: string}>
     */
    private function middle(array $a, array $b): array {
        $n = count($a);
        $m = count($b);
        if ($n === 0 || $m === 0 || $n * $m > self::MAX_CELLS) {
            return [
                ...array_map(static fn (string $line): array => ['op' => 'del', 'text' => $line], $a),
                ...array_map(static fn (string $line): array => ['op' => 'add', 'text' => $line], $b),
            ];
        }

        // LCS-Längen von hinten, eine Zeile je Index von $a.
        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $rows = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['op' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $rows[] = ['op' => 'del', 'text' => $a[$i]];
                $i++;
            } else {
                $rows[] = ['op' => 'add', 'text' => $b[$j]];
                $j++;
            }
        }
        for (; $i < $n; $i++) {
            $rows[] = ['op' => 'del', 'text' => $a[$i]];
        }
        for (; $j < $m; $j++) {
            $rows[] = ['op' => 'add', 'text' => $b[$j]];
        }

        return $rows;
    }
}
