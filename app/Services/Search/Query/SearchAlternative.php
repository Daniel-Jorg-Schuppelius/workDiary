<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchAlternative.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Query;

use App\Services\Search\SearchTextNormalizer;

/**
 * Eine Schreibweise, unter der ein Suchwort passt: Wortanfang, genaues Wort
 * oder Phrase (benachbarte Wörter). `origin` trennt die Eingabe von Synonym-
 * und Tippfehler-Varianten — die zählen für die Relevanz schwächer.
 */
final class SearchAlternative {
    public const PREFIX = 'prefix';

    public const EXACT = 'exact';

    public const PHRASE = 'phrase';

    public const ORIGIN_INPUT = 'input';

    public const ORIGIN_SYNONYM = 'synonym';

    public const ORIGIN_SIMILAR = 'similar';

    /** @param  list<string>  $words */
    public function __construct(
        public readonly string $kind,
        public readonly array $words,
        public readonly string $origin = self::ORIGIN_INPUT,
    ) {}

    /**
     * Varianten einer Wortfolge: ein Wort → Wortanfang (bis zwei Zeichen
     * genau); mehrere → Phrase ODER zusammengeschrieben („smtp relay" findet
     * „SMTP-Relay" und „SMTPRelay"). Ein-Zeichen-Wörter stehen nicht im Index.
     *
     * @param  list<string>  $words
     * @return list<self>
     */
    public static function forWords(array $words, string $origin = self::ORIGIN_INPUT, bool $phraseOnly = false): array {
        $words = array_values(array_filter($words, static fn(string $w): bool => $w !== ''));
        if ($words === []) {
            return [];
        }

        if (count($words) === 1) {
            $word = $words[0];

            return strlen($word) < 2 ? [] : [new self(strlen($word) <= 2 ? self::EXACT : self::PREFIX, [$word], $origin)];
        }

        $alternatives = [];
        $phraseWords = array_values(array_filter($words, static fn(string $w): bool => strlen($w) >= 2));
        if (count($phraseWords) >= 2) {
            $alternatives[] = new self(self::PHRASE, $phraseWords, $origin);
        }

        $joined = implode('', $words);
        if (! $phraseOnly && strlen($joined) <= SearchTextNormalizer::MAX_TOKEN_LENGTH) {
            $alternatives[] = new self(self::PREFIX, [$joined], $origin);
        }

        return $alternatives;
    }

    public function key(): string {
        return $this->kind . ':' . implode(' ', $this->words);
    }
}
