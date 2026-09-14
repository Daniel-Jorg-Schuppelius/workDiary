<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSnippet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Auszug um die erste Fundstelle, zerlegt in Segmente [Text, Treffer?] —
 * die View gibt jedes Segment escaped aus, `<mark>` setzt nur die View.
 * Suchwörter sind ASCII-gefaltet; „ue" findet deshalb auch „ü" im Original.
 */
final class SearchSnippet {
    /**
     * @param  list<list<string>>  $words  Wortfolgen aus {@see Query\ParsedSearchQuery::highlightWords()}
     * @return list<array{0: string, 1: bool}>
     */
    public static function segments(?string $text, array $words, int $length = 240): array {
        $text = StringHelper::normalizeWhitespace((string) $text);
        if ($text === '') {
            return [];
        }

        $pattern = self::pattern($words);
        if ($pattern === null || preg_match($pattern, $text, $first, PREG_OFFSET_CAPTURE) !== 1) {
            return [[StringHelper::truncate($text, $length, '…'), false]];
        }

        $position = mb_strlen(substr($text, 0, $first[0][1]));
        $start = max(0, $position - intdiv($length, 3));
        $window = mb_substr($text, $start, $length);

        $segments = [];
        if ($start > 0) {
            $segments[] = ['…', false];
        }

        $offset = 0;
        preg_match_all($pattern, $window, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$hit, $at]) {
            if ($at > $offset) {
                $segments[] = [substr($window, $offset, $at - $offset), false];
            }
            $segments[] = [$hit, true];
            $offset = $at + strlen($hit);
        }
        if ($offset < strlen($window)) {
            $segments[] = [substr($window, $offset), false];
        }
        if ($start + $length < mb_strlen($text)) {
            $segments[] = ['…', false];
        }

        return $segments;
    }

    /** @param  list<list<string>>  $words */
    private static function pattern(array $words): ?string {
        $alternatives = [];
        foreach ($words as $sequence) {
            $parts = array_map(self::wordPattern(...), array_values(array_filter($sequence, static fn(string $w): bool => $w !== '')));
            if ($parts !== []) {
                $alternatives[] = implode('[^\p{L}\p{N}]*', $parts);
            }
        }
        if ($alternatives === []) {
            return null;
        }

        usort($alternatives, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return '/(?<![\p{L}\p{N}])(?:' . implode('|', $alternatives) . ')[\p{L}\p{N}]*/iu';
    }

    private static function wordPattern(string $word): string {
        return strtr(preg_quote($word, '/'), [
            'ae' => '(?:ae|ä)',
            'oe' => '(?:oe|ö)',
            'ue' => '(?:ue|ü)',
            'ss' => '(?:ss|ß)',
        ]);
    }
}
