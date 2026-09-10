<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingInvoiceReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Services\Reselling\Marketplace\QualityHostingInvoiceReader;
use Tests\TestCase;

/**
 * Quality-Hosting-Belege aus dem zeilenausgerichteten PDF-Text (Review
 * 2026-09-10, A7/B17/B18): englisches Layout, Summenabweichung, Positionen
 * ohne Nummer, Gutschrift-Erkennung nur im Kopf, Vertrag aus der Kopfzeile.
 */
class QualityHostingInvoiceReaderTest extends TestCase {
    private const ENGLISH_TEXT = <<<'TXT'
QualityHosting GmbH- Postbox 791407 - D-11516 Berlin
Invoice
Invoice No. 31970912 Customer No. 95229
Date of Invoice September 3, 2026
Pos. Qty Description Unit price Total
End customer: CNL00007 (Klimpel Bäder GmbH)
Contract: CNLCON00156
1 1 Microsoft 365 Business Premium 187,92 187,92
Base fee per unit
Service: CNLOUI
Contract: CNLCON00156
Term: 03.09.26 - 02.09.27
End customer: CNL00010 (Schub- und Schleppreederei U. Golka GmbH &
Co.KG) Contract: CNLCON00109
2 2 Exchange Online Plan 1 34,32 68,64
Base fee per unit
Service: CNLOUK
15.08.26 - 14.08.27
Total EUR Excl. VAT 256,56
TXT;

    private const MISMATCH_TEXT = <<<'TXT'
Rechnung
Rechnungsnr. 31970913 Kundennr. 95229
Rechnungsdatum 3. September 2026
Endkunde: CNL00007 (Klimpel Bäder GmbH)
Vertrag: CNLCON00156
1 1 Microsoft 365 Business Premium 187,92 187,92
Grundgebühr pro Einheit
03.09.26 - 02.09.27
Total EUR ohne MwSt. 336,05
TXT;

    private const CREDIT_TEXT = <<<'TXT'
Storno zu Rechnung Seite 1
Gutschriftsnr. 7006872 Kundennr. 95229
Gutschriftsdatum 2. Juni 2026
Pos. Menge Beschreibung Rabatt % Einzelpreis Gesamtpreis
0 1 Umzugsbonus Endkunde Klimpel Bäder GmbH (CNL00007) 100,00 100,00
0 1 Umzugsbonus Endkunde Klimpel Bäder GmbH (CNL00007) 50,00 50,00
0 1 Umzugsbonus Endkunde Schub- und Schleppreederei U. Golka GmbH 100,00 100,00
& Co.KG (CNL00010)
Total EUR ohne MwSt. 250,00
TXT;

    private const INVOICE_WITH_STORNO_LINE = <<<'TXT'
Rechnung
Rechnungsnr. 31970914 Kundennr. 95229
Rechnungsdatum 3. September 2026
Endkunde: CNL00007 (Klimpel Bäder GmbH) Vertrag: CNLCON00156
1 1 Microsoft 365 Business Premium 187,92 187,92
Grundgebühr pro Einheit
03.09.26 - 02.09.27
2 1 Storno zu Rechnung 31970800 Gutschriftsnr. 7006999 -10,00 -10,00
Total EUR ohne MwSt. 177,92
TXT;

    public function test_english_layout_yields_contracts_companies_and_terms(): void {
        $invoice = (new QualityHostingInvoiceReader)->parse(self::ENGLISH_TEXT);

        $this->assertSame('31970912', $invoice->number);
        $this->assertSame('95229', $invoice->customerNumber);
        $this->assertSame('2026-09-03', $invoice->date?->toDateString(), '„September 3, 2026"');
        $this->assertFalse($invoice->credit);
        $this->assertTrue($invoice->isConsistent());
        $this->assertSame([], $invoice->issues);
        $this->assertCount(2, $invoice->lines);
        [$a, $b] = $invoice->lines;
        $this->assertSame('CNLCON00156', $a->contract);
        $this->assertSame('CNL00007', $a->companyKey);
        $this->assertSame('Klimpel Bäder GmbH', $a->companyName);
        $this->assertSame('2026-09-03', $a->periodStart?->toDateString(), '„Term:"-Präfix');
        $this->assertSame('2027-09-02', $a->periodEnd?->toDateString());
        $this->assertSame('CNLCON00109', $b->contract, 'Vertrag aus der umgebrochenen End-customer-Kopfzeile');
        $this->assertSame('Schub- und Schleppreederei U. Golka GmbH & Co.KG', $b->companyName);
        $this->assertSame('2026-08-15', $b->periodStart?->toDateString());
        $this->assertSame(68.64, $b->total);
    }

    public function test_total_mismatch_is_reported_but_lines_stay(): void {
        $invoice = (new QualityHostingInvoiceReader)->parse(self::MISMATCH_TEXT);

        $this->assertCount(1, $invoice->lines);
        $this->assertSame(187.92, $invoice->linesTotal());
        $this->assertSame(336.05, $invoice->netTotal);
        $this->assertFalse($invoice->isConsistent());
        $this->assertSame(__('resale_import.invoice.total_mismatch', ['lines' => '187,92', 'net' => '336,05']), $invoice->consistencyIssue());
        $this->assertSame([], $invoice->issues, 'Parse-Befunde bleiben getrennt; die Abweichung meldet der Allocator');
        $this->assertSame('CNLCON00156', $invoice->lines[0]->contract, 'Vertrag aus der Kopfzeile, wenn die Position keinen nennt');
    }

    public function test_credit_note_positions_without_number_get_running_numbers(): void {
        $credit = (new QualityHostingInvoiceReader)->parse(self::CREDIT_TEXT);

        $this->assertTrue($credit->credit);
        $this->assertSame('2026-06-02', $credit->date?->toDateString());
        $this->assertCount(3, $credit->lines);
        $this->assertSame([1, 2, 3], array_map(static fn($l) => $l->position, $credit->lines), 'Position 0 ×3 → 1, 2, 3');
        $this->assertSame(['CNL00007', 'CNL00007', 'CNL00010'], array_map(static fn($l) => $l->companyKey, $credit->lines));
        $this->assertSame([-100.0, -50.0, -100.0], array_map(static fn($l) => $l->total, $credit->lines));
        $this->assertSame(-250.0, $credit->netTotal, 'Nettobetrag der Gutschrift wie die Positionen negativ');
        $this->assertTrue($credit->isConsistent());
        $this->assertSame('Umzugsbonus', $credit->lines[2]->description);
        $this->assertNull($credit->lines[0]->contract);
    }

    public function test_storno_text_inside_a_position_does_not_make_the_invoice_a_credit_note(): void {
        $invoice = (new QualityHostingInvoiceReader)->parse(self::INVOICE_WITH_STORNO_LINE);

        $this->assertFalse($invoice->credit, 'Gutschrift nur anhand der Kopfzeilen');
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(187.92, $invoice->lines[0]->total);
        $this->assertSame(-10.0, $invoice->lines[1]->total);
        $this->assertSame('CNLCON00156', $invoice->lines[0]->contract, 'Vertrag aus der Endkunde-Kopfzeile');
        $this->assertTrue($invoice->isConsistent());
    }

    public function test_repeated_position_numbers_are_renumbered(): void {
        $invoice = (new QualityHostingInvoiceReader)->parse(<<<'TXT'
        Rechnungsnr. 1 Kundennr. 95229
        Rechnungsdatum 1. Januar 2026
        Endkunde: CNL00007 (A GmbH)
        1 1 Produkt A 10,00 10,00
        Vertrag: C-1
        Endkunde: CNL00008 (B GmbH)
        1 1 Produkt B 20,00 20,00
        Vertrag: C-2
        Endkunde: CNL00009 (C GmbH)
        3 1 Produkt C 30,00 30,00
        Vertrag: C-3
        Total EUR ohne MwSt. 60,00
        TXT);

        $this->assertSame([1, 2, 3], array_map(static fn($l) => $l->position, $invoice->lines));
        $this->assertSame(['C-1', 'C-2', 'C-3'], array_map(static fn($l) => $l->contract, $invoice->lines));
        $this->assertTrue($invoice->isConsistent());
    }
}
