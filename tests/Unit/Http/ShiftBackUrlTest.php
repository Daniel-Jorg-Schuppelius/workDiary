<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftBackUrlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Http;

use App\Http\Controllers\Concerns\ManagesShiftLike;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rücksprung nach Bereitschaft/Notdienst (`_back`): keine offene Weiterleitung.
 * Die frühere Hostprüfung ließ `/\fremd.example` durch — Browser lesen das als
 * `//fremd.example`.
 */
final class ShiftBackUrlTest extends TestCase {
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function candidates(): iterable {
        yield 'eigener Pfad' => ['/duties?tab=notdienst', '/duties?tab=notdienst'];
        yield 'eigene absolute URL' => ['https://app.example.test/duties', 'https://app.example.test/duties'];
        yield 'fremder Host' => ['https://evil.example/login', '/fallback'];
        yield 'protokollrelativ' => ['//evil.example/login', '/fallback'];
        yield 'Backslash-Trick' => ['/\\evil.example/login', '/fallback'];
        yield 'javascript' => ['javascript:alert(1)', '/fallback'];
        yield 'Schema ohne Host' => ['https:evil.example', '/fallback'];
        yield 'leer' => ['', '/fallback'];
    }

    #[DataProvider('candidates')]
    public function test_back_url_stays_on_the_own_host(string $candidate, string $expected): void {
        $subject = new class {
            use ManagesShiftLike;

            public function back(mixed $candidate): string {
                return $this->safeBackUrl($candidate, '/fallback', 'app.example.test');
            }
        };

        $this->assertSame($expected, $subject->back($candidate));
    }
}
