<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReferenceExtractorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Services\Finance\Banking\ReferenceExtractor;
use PHPUnit\Framework\TestCase;

class ReferenceExtractorTest extends TestCase {
    public function test_extracts_invoice_number_from_purpose(): void {
        $refs = ReferenceExtractor::extract('Zahlung Rechnung RE-2026-0007 Danke', null);

        $this->assertContains('RE-2026-0007', $refs);
        // normalisierte Variante (ohne Trennzeichen) ist ebenfalls enthalten.
        $this->assertContains('RE20260007', $refs);
    }

    public function test_extracts_from_end_to_end_id(): void {
        $refs = ReferenceExtractor::extract(null, 'RE-2026-0007');

        $this->assertContains('RE-2026-0007', $refs);
    }

    public function test_ignores_pure_words_without_digits(): void {
        $refs = ReferenceExtractor::extract('Danke fuer Ihren Auftrag', null);

        $this->assertSame([], $refs);
    }

    public function test_normalize_strips_separators_and_uppercases(): void {
        $this->assertSame('RE20260007', ReferenceExtractor::normalize('re-2026.0007'));
        $this->assertSame('RE20260007', ReferenceExtractor::normalize('RE 2026/0007'));
    }

    public function test_handles_empty_and_null_sources(): void {
        $this->assertSame([], ReferenceExtractor::extract(null, '', '   '));
    }
}
