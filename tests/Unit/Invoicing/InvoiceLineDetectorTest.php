<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing;

use App\Services\Invoicing\InvoiceLineDetector;
use PHPUnit\Framework\TestCase;

final class InvoiceLineDetectorTest extends TestCase {
    public function test_it_detects_lines_below_a_recognized_header_row(): void {
        $lines = (new InvoiceLineDetector)->detectFromRows([
            ['Rechnungsnummer:', 'RE-1'],
            ['Pos', 'Bezeichnung', 'Menge', 'Einheit', 'Einzelpreis', 'Betrag'],
            [1, 'Beratung', 2.0, 'Std.', 90.0, 180.0],
            [2, 'Anfahrt', 1.0, 'pauschal', 20.0, 20.0],
            ['', 'Summe netto', '', '', '', 200.0],
        ]);

        self::assertNotNull($lines);
        self::assertCount(2, $lines);
        self::assertSame('Beratung', $lines[0]['description']);
        self::assertSame('2.000', $lines[0]['quantity']);
        self::assertSame('Std.', $lines[0]['unit']);
        self::assertSame('90.0000', $lines[0]['unit_price']);
        self::assertSame('180.00', $lines[0]['amount']);
        self::assertSame('pauschal', $lines[1]['unit']);
    }

    public function test_it_rejects_tables_where_amounts_contradict_quantity_times_price(): void {
        $lines = (new InvoiceLineDetector)->detectFromRows([
            ['Bezeichnung', 'Menge', 'Einzelpreis', 'Betrag'],
            ['Beratung', 2.0, 90.0, 500.0],
        ]);

        self::assertNull($lines);
    }

    public function test_it_returns_null_without_a_header_row(): void {
        $lines = (new InvoiceLineDetector)->detectFromRows([
            ['Nettobetrag', 100.0, 'EUR'],
            ['Gesamtbetrag', 119.0, 'EUR'],
        ]);

        self::assertNull($lines);
    }

    public function test_it_parses_german_amounts_and_derives_missing_columns(): void {
        $lines = (new InvoiceLineDetector)->detectFromRows([
            ['Bezeichnung', 'Menge', 'Betrag'],
            ['Wartung', '3', '363,00 EUR'],
        ]);

        self::assertNotNull($lines);
        self::assertSame('121.0000', $lines[0]['unit_price']);
        self::assertSame('363.00', $lines[0]['amount']);
        self::assertSame('Stk.', $lines[0]['unit']);
    }

    public function test_it_reads_column_aligned_text_like_a_table(): void {
        $lines = (new InvoiceLineDetector)->detectFromAlignedText(
            "Rechnung RE-9\n" .
            "Pos   Bezeichnung        Menge   Einheit   Einzelpreis   Betrag\n" .
            "1     Serverwartung      2,00    Std.      90,00         180,00\n" .
            "2     Ersatzteil         1,00    Stk.      50,00         50,00\n" .
            "Summe netto                                              230,00\n",
        );

        self::assertNotNull($lines);
        self::assertCount(2, $lines);
        self::assertSame('Serverwartung', $lines[0]['description']);
        self::assertSame('50.00', $lines[1]['amount']);
    }
}
