<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSnippetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\Search\SearchSnippet;
use PHPUnit\Framework\TestCase;

/** Feature 153: Auszug um die Fundstelle, Hervorhebung als Segmente. */
final class SearchSnippetTest extends TestCase {
    public function test_marks_word_starts_and_folded_umlauts(): void {
        $segments = SearchSnippet::segments('Postfächer beim Kunden migriert, Exchangeserver neu', [['postfaech'], ['exchange']]);

        $marked = array_values(array_map(static fn(array $s): string => $s[0], array_filter($segments, static fn(array $s): bool => $s[1])));
        $this->assertSame(['Postfächer', 'Exchangeserver'], $marked);
        $this->assertSame('Postfächer beim Kunden migriert, Exchangeserver neu', implode('', array_column($segments, 0)));
    }

    public function test_phrases_span_separators(): void {
        $segments = SearchSnippet::segments('Sendeconnector auf SMTP-Relay umgestellt', [['smtp', 'relay']]);

        $this->assertContains(['SMTP-Relay', true], $segments);
    }

    public function test_long_texts_are_cut_around_the_first_hit(): void {
        $text = str_repeat('Füllwort ', 80) . 'Treffer ' . str_repeat('Rest ', 80);
        $segments = SearchSnippet::segments($text, [['treffer']], 60);

        $this->assertSame(['…', false], $segments[0]);
        $this->assertSame(['…', false], $segments[array_key_last($segments)]);
        $this->assertContains(['Treffer', true], $segments);
    }

    public function test_without_hit_the_text_start_is_returned(): void {
        $this->assertSame([['Kein Bezug', false]], SearchSnippet::segments('Kein Bezug', [['exchange']]));
        $this->assertSame([], SearchSnippet::segments('   ', [['exchange']]));
    }
}
