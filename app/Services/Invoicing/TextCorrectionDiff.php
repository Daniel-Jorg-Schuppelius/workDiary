<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionDiff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Erkennt in einer manuellen Textbearbeitung 1:1-Wortersetzungen als
 * Wörterbuch-Kandidaten für den „Merken?"-Dialog. Bewusst konservativ:
 * nur bei gleicher Token-Anzahl (reine Ersetzung, kein Umbau), nur
 * Tippfehler-Distanz 1–3 bzw. reine Case-Korrekturen — Synonym-Tausch
 * („Meeting" → „Besprechung") wäre als Auto-Regel gefährlich und wird
 * verworfen. Gelernt wird NIE hier, sondern erst nach Bestätigung im
 * Dialog ({@see \App\Http\Controllers\Invoicing\TextCorrectionLearnController}).
 */
final class TextCorrectionDiff {
    private const MAX_CANDIDATES = 3;

    private const MAX_DISTANCE = 3;

    /** @return list<array{wrong: string, correct: string}> */
    public static function candidates(?string $old, ?string $new): array {
        $oldTokens = self::tokens($old);
        $newTokens = self::tokens($new);

        if ($oldTokens === [] || count($oldTokens) !== count($newTokens)) {
            return [];
        }

        $candidates = [];
        foreach ($oldTokens as $i => $oldToken) {
            $wrong = self::stripPunctuation($oldToken);
            $correct = self::stripPunctuation($newTokens[$i]);

            if (! self::isPlausiblePair($wrong, $correct)) {
                continue;
            }

            $candidates[StringHelper::toLower($wrong)] = ['wrong' => $wrong, 'correct' => $correct];
            if (count($candidates) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return array_values($candidates);
    }

    private static function isPlausiblePair(string $wrong, string $correct): bool {
        if ($wrong === $correct || mb_strlen($wrong) < 2 || mb_strlen($correct) < 2) {
            return false;
        }
        if (preg_match('/\p{L}/u', $wrong) !== 1 || preg_match('/\p{L}/u', $correct) !== 1) {
            return false;
        }

        $lowWrong = StringHelper::toLower($wrong);
        $lowCorrect = StringHelper::toLower($correct);
        if ($lowWrong === $lowCorrect) {
            return true; // reine Case-Korrektur (z. B. github => GitHub)
        }

        // levenshtein ist byte-basiert — für die Tippfehler-Heuristik ausreichend.
        $distance = levenshtein($lowWrong, $lowCorrect);

        return $distance >= 1 && $distance <= self::MAX_DISTANCE;
    }

    /** @return list<string> */
    private static function tokens(?string $text): array {
        $text = StringHelper::normalizeWhitespace($text);
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $text);

        return $tokens === false ? [] : $tokens;
    }

    private static function stripPunctuation(string $token): string {
        return preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $token) ?? $token;
    }
}
