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

use App\Enums\Gaeb\GaebPhase;
use App\Services\Gaeb\GaebPreflight;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebItem, GaebTotals};
use ERechnungToolkit\Enums\{GaebItemType};
use ERechnungToolkit\Parsers\GaebDaXmlParser;
use Tests\TestCase;

/**
 * Feature 049, MVP-081: Preflight-Befund (Version, OZ-Eindeutigkeit,
 * Mengen-/Einheitenplausibilität) vor jedem Schreibvorgang.
 */
final class GaebPreflightTest extends TestCase {
    private function parsedFixture(): GaebBoq {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));

        return (new GaebDaXmlParser)->parse($xml);
    }

    /**
     * Item mit Standardwerten; nur die im Test relevanten Felder werden gesetzt.
     *
     * @param  list<string>  $components
     */
    private function item(
        string $ref = '01.0010',
        ?string $shortText = 'A',
        ?string $quantity = '1',
        ?string $unit = 'm',
        ?string $unitPrice = null,
        int $position = 0,
        array $components = [],
        bool $notOffered = false,
    ): GaebItem {
        return new GaebItem(
            reference: $ref,
            type: GaebItemType::Standard,
            shortText: $shortText,
            quantity: $quantity,
            unit: $unit,
            unitPrice: Money::ofNullable($unitPrice, CurrencyCode::Euro, 4),
            unitPriceComponents: array_map(
                static fn (string $share): Money => Money::of($share, CurrencyCode::Euro, 4),
                array_values($components)
            ),
            notOffered: $notOffered,
            position: $position,
        );
    }

    public function test_clean_file_passes(): void {
        $report = (new GaebPreflight)->check($this->parsedFixture());

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['errors']);
        $this->assertSame(4, $report['meta']['item_count']);
        $this->assertSame('3.3', $report['meta']['version']);
    }

    /** Feature 108, MVP-565: Indexpositionen sind eigenständige OZ, kein Duplikat. */
    public function test_index_positions_do_not_collide(): void {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x83_index.xml'));
        $report = (new GaebPreflight)->check((new GaebDaXmlParser)->parse($xml));

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['errors']);
        $this->assertSame(5, $report['meta']['item_count']);
    }

    public function test_duplicate_reference_is_blocking(): void {
        $items = [
            $this->item(),
            $this->item(shortText: 'B', position: 1),
        ];
        $boq = new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: $items);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
        $this->assertNotEmpty($report['errors']);
    }

    /**
     * Feature 108: In der Angebotsabgabe X84 überträgt der Bieter nur Preise und
     * Textergänzungen — Menge und Einheit dürfen dort fehlen (GAEB 3.3, Item
     * Regel 7). Vorher blockierte jede echte X84 den Import.
     */
    public function test_quantities_may_be_absent_in_bid_phase(): void {
        $items = [$this->item(quantity: null, unit: null, unitPrice: '12.50')];

        $bid = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '84', projectName: 'P', items: $items));
        $this->assertTrue($bid['ok']);

        // In der Auftragserteilung bleibt die Angabe Pflicht.
        $award = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: $items));
        $this->assertFalse($award['ok']);
    }

    public function test_missing_quantity_on_billable_item_is_blocking(): void {
        $items = [$this->item(quantity: null, unit: null)];
        $boq = new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: $items);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
        $this->assertGreaterThanOrEqual(2, count($report['errors'])); // Menge + Einheit
    }

    /** Feature 108, MVP-567: Die Anteile müssen den Einheitspreis ergeben. */
    public function test_unit_price_components_must_add_up(): void {
        $ok = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: [
            $this->item(unitPrice: '20.00', components: ['12.00', '5.00', '2.00', '1.00']),
        ]));
        $this->assertTrue($ok['ok']);

        $broken = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: [
            $this->item(unitPrice: '20.00', components: ['12.00', '5.00']),
        ]));
        $this->assertFalse($broken['ok']);
    }

    /**
     * Feature 108, MVP-569: In der X84 muss jede Position bepreist oder als
     * „nicht angeboten" gekennzeichnet sein — genau das prüft ava-sign beim
     * Reimport. Ein Preis an einer abgelehnten Position ist ebenso falsch.
     */
    public function test_bid_requires_price_or_declination(): void {
        $open = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '84', projectName: 'P', items: [
            $this->item(quantity: null, unit: null),
        ]));
        $this->assertFalse($open['ok']);

        $declined = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '84', projectName: 'P', items: [
            $this->item(quantity: null, unit: null, notOffered: true),
        ]));
        $this->assertTrue($declined['ok']);

        $contradiction = (new GaebPreflight)->check(new GaebBoq(version: '3.3', phaseCode: '84', projectName: 'P', items: [
            $this->item(quantity: null, unit: null, unitPrice: '10.00', notOffered: true),
        ]));
        $this->assertFalse($contradiction['ok']);
    }

    public function test_unsupported_version_is_blocking(): void {
        $boq = new GaebBoq(version: '2.0', phaseCode: '86', projectName: 'P', items: [$this->item(ref: '1')]);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
    }

    public function test_empty_file_is_blocking(): void {
        $boq = new GaebBoq(version: '3.3', phaseCode: '86', projectName: 'P', items: []);

        $report = (new GaebPreflight)->check($boq);

        $this->assertFalse($report['ok']);
    }

    /**
     * Angebots-Preflight (MVP-569): nimmt vorweg, was ava-sign beim Reimport
     * prüft — hier die nachgerechnete Summe und die fehlende Bieteranschrift.
     */
    public function test_export_preflight_checks_total_and_contractor(): void {
        $items = [
            $this->item(quantity: '2.000', unitPrice: '10.00'),
        ];
        $boq = new GaebBoq(
            version: '3.3',
            phaseCode: '84',
            projectName: 'P',
            items: $items,
            totals: new GaebTotals(total: Money::of('99.00', CurrencyCode::Euro, 4)),
        );

        $report = (new GaebPreflight)->checkForExport($boq, GaebPhase::Bid, false);

        $this->assertFalse($report['ok']);
        $joined = implode("\n", $report['errors']);
        $this->assertStringContainsString('99,00', $joined);
        $this->assertStringContainsString('20,00', $joined);
        $this->assertStringContainsString('Anschrift', $joined);
    }

    /** Stimmt die Summe und ist der Bieter bekannt, bleibt der Befund leer. */
    public function test_export_preflight_passes_with_matching_total(): void {
        $boq = new GaebBoq(
            version: '3.3',
            phaseCode: '84',
            projectName: 'P',
            items: [$this->item(quantity: '2.000', unitPrice: '10.00')],
            totals: new GaebTotals(total: Money::of('20.00', CurrencyCode::Euro, 4)),
        );

        $report = (new GaebPreflight)->checkForExport($boq, GaebPhase::Bid, true);

        $this->assertTrue($report['ok'], implode(' | ', $report['errors']));
    }
}
