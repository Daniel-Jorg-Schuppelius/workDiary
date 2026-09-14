<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FulltextMatchCompilerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\Search\Engine\{FulltextMatchCompiler, LikeMatchCompiler};
use App\Services\Search\Query\{ParsedSearchQuery, SearchAlternative};
use PHPUnit\Framework\TestCase;

/**
 * Feature 153: Die Testsuite läuft mit der LIKE-Engine (InnoDB-Volltext sieht
 * keine uncommitteten Zeilen). Der Boolean-Mode-Ausdruck ist deshalb hier
 * abgesichert — geprüft gegen MariaDB 10.11 am 2026-09-14.
 */
final class FulltextMatchCompilerTest extends TestCase {
    public function test_groups_are_required_and_variants_weaker(): void {
        $parsed = new ParsedSearchQuery(
            groups: [
                [
                    new SearchAlternative(SearchAlternative::PREFIX, ['exchange']),
                    new SearchAlternative(SearchAlternative::PREFIX, ['exo'], SearchAlternative::ORIGIN_SYNONYM),
                    new SearchAlternative(SearchAlternative::PHRASE, ['exchange', 'online'], SearchAlternative::ORIGIN_SYNONYM),
                ],
                [
                    new SearchAlternative(SearchAlternative::PREFIX, ['smtp']),
                    new SearchAlternative(SearchAlternative::EXACT, ['smpt'], SearchAlternative::ORIGIN_SIMILAR),
                ],
                [new SearchAlternative(SearchAlternative::EXACT, ['ad'])],
            ],
            excluded: [new SearchAlternative(SearchAlternative::PREFIX, ['test'])],
        );

        $this->assertSame(
            '+(wexchange* <wexo* "wexchange wonline") +(wsmtp* <wsmpt) +(wad) -wtest*',
            (new FulltextMatchCompiler)->expression($parsed),
        );
    }

    public function test_like_needles_share_the_semantics(): void {
        // Das escapte LIKE-Makro umschließt die Nadel mit `%` — der
        // Leerzeichen-Rahmen trennt Wortanfang, genaues Wort und Phrase.
        $this->assertSame(' wexch', LikeMatchCompiler::needle(new SearchAlternative(SearchAlternative::PREFIX, ['exch'])));
        $this->assertSame(' wad ', LikeMatchCompiler::needle(new SearchAlternative(SearchAlternative::EXACT, ['ad'])));
        $this->assertSame(' wsmtp wrelay ', LikeMatchCompiler::needle(new SearchAlternative(SearchAlternative::PHRASE, ['smtp', 'relay'])));
    }

    public function test_word_variants_follow_the_input_shape(): void {
        $single = SearchAlternative::forWords(['exchange']);
        $this->assertCount(1, $single);
        $this->assertSame(SearchAlternative::PREFIX, $single[0]->kind);

        $short = SearchAlternative::forWords(['ad']);
        $this->assertSame(SearchAlternative::EXACT, $short[0]->kind);

        $compound = SearchAlternative::forWords(['smtp', 'relay']);
        $this->assertSame(['phrase:smtp relay', 'prefix:smtprelay'], array_map(static fn(SearchAlternative $a): string => $a->key(), $compound));

        $phraseOnly = SearchAlternative::forWords(['smtp', 'relay'], SearchAlternative::ORIGIN_INPUT, true);
        $this->assertSame(['phrase:smtp relay'], array_map(static fn(SearchAlternative $a): string => $a->key(), $phraseOnly));

        // Ein-Zeichen-Teile stehen nicht im Index: nur die zusammengeschriebene Form.
        $this->assertSame(['prefix:m365'], array_map(static fn(SearchAlternative $a): string => $a->key(), SearchAlternative::forWords(['m', '365'])));
        $this->assertSame([], SearchAlternative::forWords(['a']));
    }
}
