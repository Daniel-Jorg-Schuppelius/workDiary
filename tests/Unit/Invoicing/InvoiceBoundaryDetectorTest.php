<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceBoundaryDetectorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Invoicing;

use App\Services\Invoicing\InvoicePdfImportService;
use App\Services\Invoicing\Split\InvoiceBoundaryDetector;
use PDFToolkit\Entities\WordBox;
use PHPUnit\Framework\TestCase;

/** Grenzvorschlag einer Sammel-PDF (Feature 208, MVP-1662) — erfundene Belege, reine Textauswertung. */
final class InvoiceBoundaryDetectorTest extends TestCase {
    private function detector(): InvoiceBoundaryDetector {
        return new InvoiceBoundaryDetector(new InvoicePdfImportService);
    }

    private static function invoice(string $number, string $vat, string $gross, string $marker = ''): string {
        return <<<TEXT
            Testfirma {$vat}
            Rechnung
            Rechnungsnummer: {$number}
            Rechnungsdatum: 02.11.2026
            Leistung im Oktober
            Nettobetrag 100,00 EUR
            Umsatzsteuer 19 % 19,00 EUR
            Gesamtbetrag {$gross} EUR
            USt-IdNr. {$vat}
            {$marker}
            TEXT;
    }

    public function test_three_single_page_invoices_are_three_starts(): void {
        $result = $this->detector()->propose([
            1 => self::invoice('RE-1001', 'DE111111111', '119,00'),
            2 => self::invoice('RE-1002', 'DE111111111', '119,00'),
            3 => self::invoice('AB-77', 'DE222222222', '119,00'),
        ]);

        self::assertSame([1, 2, 3], $result['starts']);
        self::assertContains('new_number', $result['signals'][2]);
        self::assertContains('after_total', $result['signals'][2]);
        self::assertContains('new_sender', $result['signals'][3]);
    }

    public function test_continuation_page_stays_with_its_invoice(): void {
        $result = $this->detector()->propose([
            1 => "Testfirma DE111111111\nRechnung\nRechnungsnummer: RE-2001\nPosition 1 bis 30\nSeite 1 von 2",
            2 => "Rechnungsnummer: RE-2001\nPosition 31 bis 40\nGesamtbetrag 238,00 EUR\nSeite 2 von 2",
            3 => self::invoice('RE-2002', 'DE111111111', '119,00', 'Seite 1 von 1'),
        ]);

        self::assertSame([1, 3], $result['starts']);
        self::assertContains('continued', $result['signals'][2]);
        self::assertContains('same_number', $result['signals'][2]);
        self::assertContains('page_one', $result['signals'][3]);
    }

    public function test_page_one_marker_alone_splits_invoices_without_numbers(): void {
        $result = $this->detector()->propose([
            1 => "Kassenbeleg\nSeite 1 von 1",
            2 => "Kassenbeleg\nSeite 1 von 1",
        ]);

        self::assertSame([1, 2], $result['starts']);
    }

    public function test_weak_signals_alone_do_not_split(): void {
        // Ein Summenblock auf Seite 1 reicht allein nicht — Seite 2 ohne Nummer, Absender oder Seitenzähler bleibt dabei.
        $result = $this->detector()->propose([
            1 => self::invoice('RE-3001', 'DE111111111', '119,00'),
            2 => "Allgemeine Geschäftsbedingungen\nEs gilt deutsches Recht.",
        ]);

        self::assertSame([1], $result['starts']);
    }

    public function test_own_identifiers_never_count_as_new_sender(): void {
        $own = ['DE999999999'];
        $result = $this->detector()->propose([
            1 => "Rechnung an DE999999999\nLieferant DE111111111\nLeistung",
            2 => "Rechnung an DE999999999\nWeitere Leistung",
        ], [], $own);

        self::assertSame([1], $result['starts']);
    }

    public function test_pages_are_rebuilt_from_word_boxes_with_letterhead(): void {
        $boxes = [
            new WordBox(1, 0.10, 0.05, 0.10, 0.02, 'Malerbetrieb'),
            new WordBox(1, 0.25, 0.05, 0.10, 0.02, 'Pinsel'),
            new WordBox(1, 0.10, 0.50, 0.20, 0.02, 'Rechnungsnummer:'),
            new WordBox(1, 0.35, 0.50, 0.10, 0.02, 'MP-1'),
            new WordBox(2, 0.10, 0.05, 0.10, 0.02, 'Dachdecker'),
            new WordBox(2, 0.10, 0.50, 0.10, 0.02, 'Text'),
        ];

        $pages = InvoiceBoundaryDetector::pagesFromBoxes($boxes, 3);

        self::assertSame("Malerbetrieb Pinsel\nRechnungsnummer: MP-1", $pages['texts'][1]);
        self::assertSame('Malerbetrieb Pinsel', $pages['headers'][1]);
        self::assertSame('Dachdecker', $pages['headers'][2]);
        self::assertSame('', $pages['texts'][3], 'Seiten ohne Wörter bleiben leer statt zu fehlen.');
    }
}
