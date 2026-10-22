<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReferenceTokensTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ReferenceTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Eine Lesart für Mail-Eingang, Rechnungsnachfrage und PDF-Erkennung (Konsolidierungs-Audit 2026-10-21, ko-08). */
final class ReferenceTokensTest extends TestCase {
    /** @return iterable<string, array{string, list<string>, list<string>}> */
    public static function texts(): iterable {
        yield 'Präfix mit Leerzeichen' => ['Frage zu RE 2026/0012', ['RE 2026/0012', 'RE2026/0012', '2026/0012'], []];
        yield 'Präfix mit Bindestrich' => ['Auftrag AB-3301 erledigt', ['AB-3301'], []];
        yield 'klein geschrieben' => ['siehe re-2026-7', ['re-2026-7', 'RE-2026-7'], []];
        yield 'Bestellnummer mit Punkten' => ['Ihre Bestellung B.2026.15', ['B.2026.15'], []];
        yield 'ohne Ziffer kein Kandidat' => ['Rechnung Kunde', [], ['Rechnung', 'Kunde', 'RECHNUNG']];
    }

    /**
     * @param list<string> $expected
     * @param list<string> $absent
     */
    #[DataProvider('texts')]
    public function test_tokens(string $text, array $expected, array $absent): void {
        $tokens = ReferenceTokens::fromText($text);
        foreach ($expected as $token) {
            $this->assertContains($token, $tokens);
        }
        foreach ($absent as $token) {
            $this->assertNotContains($token, $tokens);
        }
    }

    public function test_limit_is_respected(): void {
        $text = implode(' ', array_map(static fn (int $i): string => 'RE-' . (1000 + $i), range(1, 50)));

        $this->assertCount(10, ReferenceTokens::fromText($text, 10));
    }

    public function test_key_ignores_whitespace_and_case(): void {
        $this->assertSame('RE2026/0012', ReferenceTokens::key(' re 2026/0012 '));
        $this->assertSame(ReferenceTokens::key('RE 2026/0012'), ReferenceTokens::key('re2026/0012'));
        $this->assertNull(ReferenceTokens::key('   '));
        $this->assertNull(ReferenceTokens::key(null));
        $this->assertSame(64, mb_strlen((string) ReferenceTokens::key(str_repeat('A', 80))));
    }
}
