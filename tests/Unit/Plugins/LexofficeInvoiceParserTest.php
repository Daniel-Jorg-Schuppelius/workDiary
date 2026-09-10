<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceParserTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\Lexoffice\LexofficeInvoiceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lexoffice-Rechnungsparser (Feature 152, Review 2026-09-10): Leistungszeitraum
 * aus dem Belegtext (A13), Positionsbetrag aus `lineItemAmount`, fehlender
 * Steuersatz als Hinweis statt stiller Rate 0.
 */
class LexofficeInvoiceParserTest extends TestCase {
    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function periodTexts(): iterable {
        yield 'Jahreswechsel mit Gedankenstrich' => ['Leistungszeitraum 15.12.2025 – 14.12.2026', '2025-12-15', '2026-12-14'];
        yield 'Bindestrich ohne Leerzeichen' => ['Microsoft 365 (01.01.2026-31.12.2026)', '2026-01-01', '2026-12-31'];
        yield 'bis-Schreibweise' => ['Lizenzen vom 01.03.2026 bis 28.02.2027', '2026-03-01', '2027-02-28'];
        yield 'Beginn ohne Jahr, Jahr vom Ende' => ['Abrechnung 01.04. – 30.06.2026', '2026-04-01', '2026-06-30'];
        yield 'Beginn ohne Jahr über den Jahreswechsel' => ['Zeitraum 15.12. – 14.01.2026', '2025-12-15', '2026-01-14'];
        yield 'zweistellige Jahre' => ['Support 01.07.26 bis 30.06.27', '2026-07-01', '2027-06-30'];
        yield 'deutscher Monatsname' => ['Hosting Januar 2026', '2026-01-01', '2026-01-31'];
        yield 'gekürzter Monat mit Punkt' => ['Backup Feb. 2028 (Schaltjahr)', '2028-02-01', '2028-02-29'];
        yield 'numerischer Monat' => ['Leistungszeitraum 03/2026', '2026-03-01', '2026-03-31'];
        yield 'englischer Monatsname' => ['Service period December 2026', '2026-12-01', '2026-12-31'];
        yield 'englischer Datumsbereich mit to' => ['Licences 01.02.2026 to 31.01.2027', '2026-02-01', '2027-01-31'];
        yield 'Monatsspanne mit einem Jahr' => ['Lizenzen Januar – März 2026', '2026-01-01', '2026-03-31'];
        yield 'Monatsspanne mit zwei Jahren (englisch)' => ['Subscription November 2025 to February 2026', '2025-11-01', '2026-02-28'];
        yield 'Ende vor Beginn wird verworfen' => ['Zeitraum 14.12.2026 – 15.12.2025', null, null];
        yield 'ungültiges Datum wird verworfen' => ['Zeitraum 31.02.2026 – 30.03.2026', null, null];
        yield 'Rechnungsnummer ist kein Monat' => ['Rechnung RE/2026/0001, vielen Dank', null, null];
        yield 'kein Zeitraum' => ['Vielen Dank für die gute Zusammenarbeit', null, null];
    }

    #[DataProvider('periodTexts')]
    public function test_service_period_from_text(string $text, ?string $from, ?string $to): void {
        $this->assertSame([$from, $to], LexofficeInvoiceParser::servicePeriodFromText($text));
    }

    public function test_structured_shipping_conditions_win_over_text(): void {
        $parsed = LexofficeInvoiceParser::parse([
            'introduction' => 'Leistungszeitraum 01.01.2026 – 31.12.2026', 'lineItems' => [],
            'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'serviceperiod'],
        ]);
        $this->assertSame(['2025-08-05', '2026-08-04'], [$parsed['service_from'], $parsed['service_to']]);

        // Einzeldatum ist eine strukturierte Angabe — der Text ergänzt kein Ende.
        $single = LexofficeInvoiceParser::parse([
            'introduction' => 'Leistungszeitraum 01.01.2026 – 31.12.2026', 'lineItems' => [],
            'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'service'],
        ]);
        $this->assertSame(['2025-08-05', null], [$single['service_from'], $single['service_to']]);

        $fallback = LexofficeInvoiceParser::parse(['title' => 'Rechnung', 'introduction' => 'Microsoft 365 für Klimpel Bäder, 15.12.2025 – 14.12.2026', 'remark' => 'Danke', 'lineItems' => []]);
        $this->assertSame(['2025-12-15', '2026-12-14'], [$fallback['service_from'], $fallback['service_to']]);
    }

    public function test_line_item_amount_is_preferred_over_recalculation(): void {
        $net = LexofficeInvoiceParser::parse([
            'taxConditions' => ['taxType' => 'net'],
            'lineItems' => [
                ['type' => 'custom', 'name' => 'A', 'quantity' => 3, 'unitPrice' => ['netAmount' => 33.333, 'grossAmount' => 39.67, 'taxRatePercentage' => 19], 'lineItemAmount' => 100.0],
                ['type' => 'custom', 'name' => 'B', 'quantity' => 2, 'unitPrice' => ['grossAmount' => 107.10, 'taxRatePercentage' => 19], 'discountPercentage' => 10],
            ],
        ]);
        $this->assertSame(100.0, $net['lines'][0]['total_net'], 'lineItemAmount der API statt 3 × 33,333 = 99,999');
        $this->assertSame(33.333, $net['lines'][0]['unit_net']);
        $this->assertSame(81.0, $net['lines'][1]['unit_net'], '107,10 brutto → 90 netto − 10 % Rabatt');
        $this->assertSame(162.0, $net['lines'][1]['total_net'], 'ohne lineItemAmount weiter gerechnet');
        $this->assertSame([], $net['issues']);

        // Bruttorechnung: lineItemAmount ist brutto und wird über den Steuersatz umgerechnet.
        $gross = LexofficeInvoiceParser::parse([
            'taxConditions' => ['taxType' => 'gross'],
            'lineItems' => [['type' => 'custom', 'name' => 'C', 'quantity' => 1, 'unitPrice' => ['netAmount' => 20.08, 'grossAmount' => 23.9, 'taxRatePercentage' => 19], 'lineItemAmount' => 23.90]],
        ]);
        $this->assertSame(20.08, $gross['lines'][0]['total_net']);
    }

    public function test_missing_tax_rate_with_gross_price_is_reported(): void {
        $parsed = LexofficeInvoiceParser::parse([
            'lineItems' => [['type' => 'custom', 'name' => 'Pauschale', 'quantity' => 1, 'unitPrice' => ['grossAmount' => 119.0]]],
        ]);
        $this->assertSame(119.0, $parsed['lines'][0]['unit_net'], 'Brutto = Netto nur mit Hinweis');
        $this->assertNull($parsed['lines'][0]['tax_rate'], 'kein stiller Steuersatz 0');
        $this->assertCount(1, $parsed['issues']);
        $this->assertStringContainsString('Pauschale', $parsed['issues'][0]);
    }
}
