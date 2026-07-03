<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebDaXmlParserTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Gaeb;

use App\Enums\Gaeb\BoqItemType;
use App\Services\Gaeb\{GaebDaXmlParser, GaebParseException};
use Tests\TestCase;

/**
 * Feature 049, MVP-081: GAEB-DA-XML-Parser. Version/Phase, Hierarchie,
 * Ordnungszahlen, Texte, Mengen/Einheiten und Positionsart.
 */
final class GaebDaXmlParserTest extends TestCase {
    private function fixture(): string {
        return (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
    }

    public function test_extracts_version_phase_and_project(): void {
        $boq = (new GaebDaXmlParser)->parse($this->fixture());

        $this->assertSame('3.3', $boq->version);
        $this->assertSame('86', $boq->phase);
        $this->assertSame('Neubau Lagerhalle Musterstadt', $boq->projectName);
    }

    public function test_builds_section_hierarchy_and_ordinal_refs(): void {
        $boq = (new GaebDaXmlParser)->parse($this->fixture());

        $this->assertSame(2, $boq->sectionCount());
        $refs = array_column($boq->sections, 'ref');
        $this->assertEqualsCanonicalizing(['01', '02'], $refs);
        $this->assertSame('Erdarbeiten', $boq->sections[0]['label']);
    }

    public function test_parses_items_with_text_quantity_and_price(): void {
        $boq = (new GaebDaXmlParser)->parse($this->fixture());

        $this->assertSame(4, $boq->itemCount());

        $byRef = [];
        foreach ($boq->items as $item) {
            $byRef[$item['ref']] = $item;
        }

        $this->assertArrayHasKey('01.0010', $byRef);
        $main = $byRef['01.0010'];
        $this->assertSame('Boden lösen', $main['short_text']);
        $this->assertStringContainsString('Bodenklasse 3-5', (string) $main['long_text']);
        $this->assertSame('100.000', $main['quantity']);
        $this->assertSame('m3', $main['unit']);
        $this->assertSame('12.50', $main['unit_price']);
        $this->assertSame('01', $main['section_ref']);
        $this->assertSame(BoqItemType::Standard->value, $main['type']);

        // Maurerarbeiten-Position unter Abschnitt 02.
        $this->assertSame('02', $byRef['02.0010']['section_ref']);
    }

    public function test_detects_optional_and_note_item_types(): void {
        $boq = (new GaebDaXmlParser)->parse($this->fixture());

        $byRef = [];
        foreach ($boq->items as $item) {
            $byRef[$item['ref']] = $item;
        }

        $this->assertSame(BoqItemType::Optional->value, $byRef['01.0020']['type']);  // Provis
        $this->assertSame(BoqItemType::Note->value, $byRef['01.0001']['type']);      // ohne Menge/Einheit
    }

    /**
     * Feature 052 (bewusste Lockerung, A2): DE-Tausenderformat "1.234,56" wird
     * jetzt als 1234.56 geparst (vorher null); NBSP-Gruppierung ebenso.
     * Nicht-numerischer Müll bleibt weiterhin null.
     */
    public function test_parses_thousand_separated_numbers_and_keeps_garbage_null(): void {
        $nbsp = "\u{00A0}";
        $payload = <<<XML
<?xml version="1.0"?>
<GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA86/3.3"><Award><BoQ><BoQBody><Itemlist><Item RNoPart="1"><Qty>1.234,56</Qty><QU>m3</QU><UP>1{$nbsp}234,50</UP><IT>abc</IT></Item></Itemlist></BoQBody></BoQ></Award></GAEB>
XML;

        $boq = (new GaebDaXmlParser)->parse($payload);
        $item = $boq->items[0];

        $this->assertSame('1234.56', $item['quantity']);
        $this->assertSame('1234.50', $item['unit_price']);
        $this->assertNull($item['total_price']);
    }

    public function test_rejects_non_gaeb_xml(): void {
        $this->expectException(GaebParseException::class);
        (new GaebDaXmlParser)->parse('<root><foo/></root>');
    }

    /** Feature 051: XXE-Härtung — DOCTYPE wird abgewiesen, Entities nicht expandiert. */
    public function test_rejects_doctype_to_prevent_xxe(): void {
        $payload = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE GAEB [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>
<GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA86/3.3"><Award><BoQ><BoQBody><Itemlist><Item RNoPart="1"><Qty>1</Qty><QU>&xxe;</QU></Item></Itemlist></BoQBody></BoQ></Award></GAEB>
XML;

        $this->expectException(GaebParseException::class);
        (new GaebDaXmlParser)->parse($payload);
    }
}
