<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPreflightTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Gaeb;

use App\Services\Gaeb\{GaebDaXmlParser, GaebPreflight, ParsedBoq};
use Tests\TestCase;

/**
 * Feature 049, MVP-081: Preflight-Befund (Version, OZ-Eindeutigkeit,
 * Mengen-/Einheitenplausibilität) vor jedem Schreibvorgang.
 */
final class GaebPreflightTest extends TestCase {
    private function parsedFixture(): ParsedBoq {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));

        return (new GaebDaXmlParser)->parse($xml);
    }

    public function test_clean_file_passes(): void {
        $report = (new GaebPreflight)->check($this->parsedFixture());

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['errors']);
        $this->assertSame(4, $report['meta']['item_count']);
        $this->assertSame('3.3', $report['meta']['version']);
    }

    public function test_duplicate_reference_is_blocking(): void {
        $items = [
            ['ref' => '01.0010', 'section_ref' => null, 'type' => 'standard', 'short_text' => 'A', 'long_text' => null, 'quantity' => '1', 'unit' => 'm', 'unit_price' => null, 'total_price' => null, 'is_addendum' => false, 'external_id' => null, 'position' => 0],
            ['ref' => '01.0010', 'section_ref' => null, 'type' => 'standard', 'short_text' => 'B', 'long_text' => null, 'quantity' => '1', 'unit' => 'm', 'unit_price' => null, 'total_price' => null, 'is_addendum' => false, 'external_id' => null, 'position' => 1],
        ];
        $boq = new ParsedBoq('3.3', '86', 'P', null, [], $items);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
        $this->assertNotEmpty($report['errors']);
    }

    public function test_missing_quantity_on_billable_item_is_blocking(): void {
        $items = [
            ['ref' => '01.0010', 'section_ref' => null, 'type' => 'standard', 'short_text' => 'A', 'long_text' => null, 'quantity' => null, 'unit' => null, 'unit_price' => null, 'total_price' => null, 'is_addendum' => false, 'external_id' => null, 'position' => 0],
        ];
        $boq = new ParsedBoq('3.3', '86', 'P', null, [], $items);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
        $this->assertGreaterThanOrEqual(2, count($report['errors'])); // Menge + Einheit
    }

    public function test_unsupported_version_is_blocking(): void {
        $boq = new ParsedBoq('2.0', '86', 'P', null, [], [
            ['ref' => '1', 'section_ref' => null, 'type' => 'standard', 'short_text' => 'A', 'long_text' => null, 'quantity' => '1', 'unit' => 'm', 'unit_price' => null, 'total_price' => null, 'is_addendum' => false, 'external_id' => null, 'position' => 0],
        ]);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
    }

    public function test_empty_file_is_blocking(): void {
        $boq = new ParsedBoq('3.3', '86', 'P', null, [], []);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
    }
}
