<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePdfImportServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Invoicing;

use App\Services\Invoicing\InvoicePdfImportService;
use PHPUnit\Framework\TestCase;

final class InvoicePdfImportServiceTest extends TestCase {
    public function test_it_extracts_core_invoice_fields_from_german_text(): void {
        $result = (new InvoicePdfImportService)->analyzeText(<<<'TEXT'
            Muster GmbH
            Rechnung
            Rechnungsnummer: RE-2026-0042
            Rechnungsdatum: 14.08.2026
            Fällig am: 28.08.2026
            Leitweg-ID: 991-12345-67
            Nettobetrag 1.000,00 EUR
            Umsatzsteuer 19 % 190,00 EUR
            Gesamtbetrag 1.190,00 EUR
            TEXT);

        self::assertSame('RE-2026-0042', $result['number']);
        self::assertSame('2026-08-14', $result['issued_on']);
        self::assertSame('2026-08-28', $result['due_on']);
        self::assertSame('991-12345-67', $result['buyer_reference']);
        self::assertSame('1000.00', $result['net']);
        self::assertSame('190.00', $result['tax']);
        self::assertSame('1190.00', $result['gross']);
        self::assertSame('19.00', $result['tax_rate']);
        self::assertSame([], $result['warnings']);
    }

    /** Befund 2026-10-18: Lieferanten schreiben „Rechnung Nr.“, „Rechnungs-Nr.:“, „Re.-Nr.“, „Gesamtsumme“, „Endbetrag“ — die Klärfälle entstanden an der Beschriftung. */
    public function test_it_recognises_common_label_variants_for_number_and_gross(): void {
        $service = new InvoicePdfImportService;
        $cases = [
            ["Rechnung Nr. 4711\nGesamtsumme 119,00 EUR", '4711', '119.00'],
            ["Rechnungs-Nr.: RE-2025-0042\nEndbetrag: 1.190,00 €", 'RE-2025-0042', '1190.00'],
            ["Re.-Nr. 2025/17\nSumme brutto 59,50", '2025/17', '59.50'],
            ["Rg.-Nr. 88\nzu zahlen: 12,02 EUR", '88', '12.02'],
            ["Beleg-Nr. B-77\nGesamt brutto 10,25 EUR", 'B-77', '10.25'],
            ["Invoice # INV-9\nTotal amount EUR 38.35", 'INV-9', '38.35'],
            ["RECHNUNGSNUMMER 2501202\nRechnungssumme 38,35 EUR", '2501202', '38.35'],
        ];
        foreach ($cases as [$text, $number, $gross]) {
            $result = $service->analyzeText($text);
            self::assertSame($number, $result['number'], $text);
            self::assertSame($gross, $result['gross'], $text);
        }
    }

    /** Befund 2026-10-20: „[\d .]+“ nahm ein Datum vor dem Betrag mit — aus „16.03.2025 201,93“ wurden 16.032.025.201,93 EUR im Belegspiegel. */
    public function test_a_date_in_front_of_an_amount_never_becomes_part_of_the_amount(): void {
        $service = new InvoicePdfImportService;

        $result = $service->analyzeText(<<<'TEXT'
            Rechnung
            Rechnungsnummer: 250723880
            Rechnungsdatum: 14.02.2025
            Zwischensumme 169,69 EUR
            MwSt. 19 % 16.03.2025 32,24 EUR
            Gesamtbetrag 16.03.2025 201,93 EUR
            TEXT);

        self::assertSame('250723880', $result['number']);
        self::assertSame('2025-02-14', $result['issued_on']);
        self::assertSame('169.69', $result['net']);
        self::assertNull($result['tax'], 'Steht hinter der Beschriftung ein Datum, bleibt das Feld leer.');
        self::assertNull($result['gross'], 'Steht hinter der Beschriftung ein Datum, bleibt das Feld leer.');

        self::assertNull($service->analyzeText("Rechnungsbetrag 16.03.2025\nNetto 100,00")['gross'], 'Ein Datum ist kein Betrag (vorher 1.603,20).');
        self::assertNull($service->analyzeText("Umsatzsteuer 19 % 16.03.2025")['tax']);

        $cases = [
            ['Gesamtbetrag 1.190,00 EUR', '1190.00'],
            ['Gesamtbetrag 1 190,00 EUR', '1190.00'],
            ['Gesamtbetrag 12.345.678,90 EUR', '12345678.90'],
            ['Gesamtbetrag: 0,00', '0.00'],
            ['Total 1,234.56', '1234.56'],
            ['Total USD 38.35', '38.35'],
            ['Endbetrag -12,50 €', '0.00'],
            ['Gesamtbetrag EUR 1.190,00', '1190.00'],
            ["Gesamtbetrag:\n1.190,00 EUR", '1190.00'],
            ["Total CHF 1'234.50", '1234.50'],
            ['Gesamtbetrag (inkl. Versand 8,20 EUR) 1.190,00 EUR', '1190.00'],
        ];
        foreach ($cases as [$text, $gross]) {
            self::assertSame($gross, $service->analyzeText($text)['gross'], $text);
        }
        self::assertNull($service->analyzeText('Gesamtbetrag fällig am 16.03.2025 201,93 EUR')['gross'], 'Zwischen Beschriftung und Betrag steht höchstens eine Währung.');
    }

    /** Währung aus dem common-toolkit (CurrencyCode): am Betrag, sonst als Code oder Symbol im Text, sonst EUR. */
    public function test_it_reads_the_document_currency_from_the_amounts_or_the_text(): void {
        $service = new InvoicePdfImportService;

        self::assertSame('EUR', $service->analyzeText("Gesamtbetrag 1.190,00 EUR")['currency']);
        self::assertSame('CHF', $service->analyzeText("Total CHF 1'234.50")['currency']);
        self::assertSame('USD', $service->analyzeText("Total $ 38.35")['currency']);
        self::assertSame('GBP', $service->analyzeText("Total £38.35")['currency']);
        self::assertSame('AUD', $service->analyzeText("Total AU$ 38.35")['currency']);
        self::assertSame('GBP', $service->analyzeText("Alle Preise in GBP\nTotal 10.00")['currency'], 'Ohne Währung am Betrag zählt der genannte Code.');
        self::assertSame('EUR', $service->analyzeText("Rechnung\nGesamtbetrag 10,00")['currency']);
        self::assertSame('EUR', $service->analyzeText("USD-Konto siehe Anhang\nNetto 10,00 EUR\nGesamtbetrag 11,90 EUR")['currency'], 'Die Währung an den Beträgen schlägt eine Erwähnung im Text.');
        self::assertSame('EUR', $service->analyzeText("z. Hd. Fr. Müller\nGesamtbetrag 10,00")['currency'], '„Fr.“ als Anrede ist keine Währung.');
        self::assertSame('CHF', $service->analyzeText("Total Fr. 1'234.50")['currency'], '„Fr.“ am Betrag ist der Franken (common-toolkit ≥ 2.12.2).');
        self::assertSame('1234.50', $service->analyzeText("Total Fr. 1'234.50")['gross']);
        self::assertSame('USD', $service->analyzeText('Total US$ 38.35')['currency'], 'Bis v2.12.1 las der Tokenizer „S$“ (Singapur) heraus.');
    }

    public function test_it_ignores_number_labels_without_digits_and_unrelated_nr_labels(): void {
        $service = new InvoicePdfImportService;

        self::assertNull($service->analyzeText("Rechnungsnummer siehe oben\nGesamtbetrag 10,00 EUR")['number']);
        self::assertNull($service->analyzeText("Ihre Nr. 12345\nKunden-Nr. 777\nGesamtbetrag 10,00 EUR")['number']);
        self::assertNull($service->analyzeText("Gesamt netto 100,00 EUR")['gross'], '„Gesamt netto“ ist kein Bruttobetrag.');
    }

    public function test_it_derives_missing_net_and_reports_uncertain_fields(): void {
        $result = (new InvoicePdfImportService)->analyzeText(<<<'TEXT'
            Invoice date: 2026-08-14
            VAT 7 % 7,00 EUR
            Amount due 107,00 EUR
            TEXT);

        self::assertSame('100.00', $result['net']);
        self::assertSame('7.00', $result['tax_rate']);
        self::assertContains('missing_number', $result['warnings']);
        self::assertNotContains('missing_net', $result['warnings']);
    }

    public function test_it_reads_docx_without_executing_embedded_content(): void {
        $path = tempnam(sys_get_temp_dir(), 'invoice-docx-') . '.docx';
        $zip = new \ZipArchive;
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
              <w:p><w:r><w:t>Rechnungsnummer: DOCX-2026-8</w:t></w:r></w:p>
              <w:p><w:r><w:t>Rechnungsdatum: 14.08.2026</w:t></w:r></w:p>
              <w:p><w:r><w:t>Nettobetrag 100,00 EUR</w:t></w:r></w:p>
              <w:p><w:r><w:t>Umsatzsteuer 19 % 19,00 EUR</w:t></w:r></w:p>
              <w:p><w:r><w:t>Gesamtbetrag 119,00 EUR</w:t></w:r></w:p>
            </w:body></w:document>
            XML);
        $zip->close();

        try {
            $result = (new InvoicePdfImportService)->extract($path, 'docx');
            self::assertSame('DOCX-2026-8', $result['number']);
            self::assertSame('100.00', $result['net']);
            self::assertSame('docx', $result['reader']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_xlsx_through_common_toolkit_parser(): void {
        $path = tempnam(sys_get_temp_dir(), 'invoice-xlsx-') . '.xlsx';
        $builder = (new \CommonToolkit\Builders\XLSXDocumentBuilder)
            ->sheet('Rechnung')
            ->addRows([
                ['Rechnungsnummer:', 'XLSX-2026-9'],
                ['Rechnungsdatum:', '14.08.2026'],
                ['Nettobetrag', 100.00, 'EUR'],
                ['Umsatzsteuer 19 %', 19.00, 'EUR'],
                ['Gesamtbetrag', 119.00, 'EUR'],
            ]);
        \CommonToolkit\Generators\XLSX\XLSXGenerator::toFile($builder->build(), $path);

        try {
            $result = (new InvoicePdfImportService)->extract($path, 'xlsx');
            self::assertSame('XLSX-2026-9', $result['number']);
            self::assertSame('100.00', $result['net']);
            self::assertSame('xlsx-toolkit', $result['reader']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_labels_and_values_from_separate_blocks_and_from_header_rows(): void {
        $service = new InvoicePdfImportService;

        // F1: pdftotext gibt Spaltenblöcke aus — erst alle Beschriftungen, dann alle Werte.
        $blocks = $service->analyzeText("Rechnung\nRechnungsnummer\nRechnungsdatum\nKundennummer\nHerr Max Muster\nMusterweg 1\n12345 Musterstadt\n9653702\n30.07.2025\n23940\nGesamtwert netto\nMwSt. 19,00 %\nGesamtbetrag\n100,00 €\n19,00 €\n119,00 €");
        self::assertSame('9653702', $blocks['number']);
        self::assertSame('2025-07-30', $blocks['issued_on']);
        self::assertSame('blocks', $blocks['sources']['number']);

        // F2: Kopfzeile über Wertezeile, wie im zeilentreuen Text.
        $rows = $service->analyzeText("Belegnummer   Erstellt am   Gesamtbetrag   Fällig am\nAB-4711   12.03.2026   1.190,00 €   26.03.2026");
        self::assertSame('AB-4711', $rows['number']);
        self::assertSame('2026-03-12', $rows['issued_on']);
        self::assertSame('1190.00', $rows['gross']);
        self::assertSame('2026-03-26', $rows['due_on']);
        self::assertSame('rows', $rows['sources']['gross']);

        // Der zeilentreue Zweittext füllt, was der Lesetext schuldig bleibt — und gewinnt bei stimmigen Summen.
        $merged = $service->analyzeText(
            "Rechnung Nr. DR8824717\nBelegdatum\n21.08.2025\nGesamt EUR ohne MwSt.\nMwSt.-Betrag (19%)\nGesamt EUR inkl. MwSt.\n468,41\n89,00\n557,41",
            "Rechnung Nr. DR8824717\nBelegdatum 21.08.2025\nGesamt EUR ohne MwSt. 468,41\nMwSt.-Betrag (19%) 89,00\nZahlbar: 8 Tage nach Rechnungsdatum netto  Gesamt EUR inkl. MwSt. 557,41",
        );
        self::assertSame('DR8824717', $merged['number']);
        self::assertSame('2025-08-21', $merged['issued_on']);
        self::assertSame('557.41', $merged['gross']);
        self::assertSame('468.41', $merged['net']);
        self::assertSame([], $merged['warnings']);
    }

    public function test_it_knows_more_total_and_date_spellings_and_never_takes_a_net_line_as_gross(): void {
        $service = new InvoicePdfImportService;

        $cases = [
            ["Rechnungsnummer: INV2025-3518408\nRechnungsdatum: 27.08.2025\nGesamtpreis (exkl. MwSt.):\n28,12 €\nMwSt. 19% (19%):\n5,34 €\nGesamtpreis (inkl. MwSt.):\n33,46 €", '33.46', '28.12'],
            ["Rechnung Nr. 14192399\nGesamtbetrag (Versandkostenanteil 8,20 EUR) 48,20 EUR", '48.20', null],
            ["Rechnung Nr. IN4046824113\nRechnungsbetrag Netto 100,00 EUR\nRechnungsbetrag Brutto 119,00 EUR", '119.00', '100.00'],
            ["Rechnung Nr. 77\nGesamtpreis Netto 102,54 €\nSumme inkl. MwSt. 122,02 €", '122.02', '102.54'],
        ];
        foreach ($cases as [$text, $gross, $net]) {
            $result = $service->analyzeText($text);
            self::assertSame($gross, $result['gross'], $text);
            if ($net !== null) {
                self::assertSame($net, $result['net'], $text);
            }
        }

        $placeDate = $service->analyzeText("Rechnungs-Nr: AR20250831AD012409   Starnberg, 31.08.2025\nGesamtbetrag 127,56 €");
        self::assertSame('2025-08-31', $placeDate['issued_on']);
        $bareDate = $service->analyzeText("Rechnungsnummer 4711\nDatum: 07.07.2025\nGesamtbetrag 16,51");
        self::assertSame('2025-07-07', $bareDate['issued_on']);
        $delivery = $service->analyzeText("Rechnung Nr. 14192399\nLieferdatum : 05.08.25\nGesamtbetrag 48,20 EUR");
        self::assertNull($delivery['issued_on'], 'Ein Lieferdatum ist kein Rechnungsdatum.');
        self::assertSame('2025-08-05', $delivery['issued_on_hint']);
        self::assertNull($service->analyzeText("Bestelldatum: 01.02.2025\nRechnung Nr. 1")['issued_on'], '„Bestelldatum“ ist kein nacktes „Datum“.');
    }

    public function test_it_hints_the_document_kind_for_reminders_and_other_non_invoices(): void {
        $service = new InvoicePdfImportService;

        $reminder = $service->analyzeText("Kundennummer: 1804771\nDatum: 07.07.2025\n2. Mahnung\nReNr. Datum Fällig Belegart Betrag Mahnbetrag\nRZLRE2501475 17.06.2025 17.06.2025 Rechnung 13,51 13,51 2\nMahngebühren: 3,00\nZu zahlen: 16,51");
        self::assertSame('reminder', $reminder['kind_hint']);
        self::assertSame(['RZLRE2501475'], $reminder['reminder_numbers']);
        self::assertNull($reminder['number'], 'Die Nummer der angemahnten Rechnung ist nicht die Nummer der Mahnung.');

        $label = $service->analyzeText("DHL Online Frankierung\nPaket bis 20 kg\nSendungsnummer: 91.000.326471 3\nEntgelt Bezahlt\nAbsender: Daniel Muster");
        self::assertSame('shipping_label', $label['kind_hint']);

        self::assertSame('delivery_note', $service->analyzeText("Lieferschein\nLieferschein-Nr. 5566\nMenge Artikel\n1 Schraube")['kind_hint']);
        self::assertSame('order_confirmation', $service->analyzeText("Auftragsbestätigung\nAuftragsnummer 99\nGesamtbetrag 10,00 EUR")['kind_hint']);
        self::assertNull($service->analyzeText("Rechnung\nRechnungsnummer 1\nLieferschein-Nr. 5\nGesamtbetrag 10,00 EUR")['kind_hint'], 'Ein Rechnungstitel schlägt den Lieferscheinverweis.');
    }

    /** Fehlerklassen aus Phase 155 (MVP-1251) an synthetischen Texten: Summen mit Zusatz, Prozentsätze, Spaltenblöcke mit Währung. */
    public function test_it_closes_total_label_gaps_of_phase_155(): void {
        $service = new InvoicePdfImportService;

        // „Total EUR inkl. MwSt.“ und Kopfzeile „MwSt. Netto-Betrag MwSt.-Betrag“ über der Wertezeile (Musikhändler-Form).
        $total = $service->analyzeText("Rechnung-Nr.   TN-100200\nRechnungsdatum:   28.03.2025\nSumme   2   Total EUR inkl. MwSt.   11,90\nMwSt.   Netto-Betrag   MwSt.-Betrag\nGesamt EUR   10,00   1,90");
        self::assertSame('11.90', $total['gross']);
        self::assertSame('10.00', $total['net']);
        self::assertSame('1.90', $total['tax']);
        self::assertSame([], $total['warnings']);

        // Steuerzusatz hinter der Beschriftung (Versicherungsform).
        $insurance = $service->analyzeText("Rechnungs-Nr.:   90001\nRechnungsbetrag inkl. Versicherungssteuer   100,00\nDen o.g. Rechnungsbetrag ziehen wir am 19.03.25 per Lastschrift ein.");
        self::assertSame('100.00', $insurance['gross']);

        // Ein Betrag vor einem Prozentzeichen ist ein Satz, keine Summe (Baumarkt-Form).
        $percent = $service->analyzeText("Rechnung\nRechnungsbetrag Brutto   59,50\nRechnungsbetrag Netto   50,00\nRechnungsbetrag wurde bereits bezahlt.\nIm Endbetrag sind 19,00% Umsatzsteuer ( 9,50 € aus 59,50 € ) enthalten.");
        self::assertSame('59.50', $percent['gross']);
        self::assertSame('50.00', $percent['net']);

        // Bemessungsgrundlage hinter „aus“ ist nicht die Steuer; „Summe Nettowert“; Auftragsnummer als Belegnummer mit Hinweis (Marktplatz-Form).
        $marketplace = $service->analyzeText("Rechnung von Marktplatz AG im Namen und Auftrag von\nRechnungsdatum 09.01.25\nAuftragsnummer: 5001234567-00-A\nSumme Nettowert   EUR   40,00\nMwSt (01) 19.0% aus 40,00 EUR   EUR   7,60\nEndbetrag   EUR   47,60");
        self::assertSame('47.60', $marketplace['gross']);
        self::assertSame('40.00', $marketplace['net']);
        self::assertSame('7.60', $marketplace['tax']);
        self::assertSame('5001234567-00-A', $marketplace['number']);
        self::assertSame('order_reference', $marketplace['number_hint']);
        self::assertSame('order', $marketplace['sources']['number']);
        self::assertNull($service->analyzeText("Rechnungsnummer siehe Anlage\nAuftragsnummer: 4711\nEndbetrag 1,00")['number'], 'Mit Rechnungsnummer-Beschriftung bleibt die Auftragsnummer außen vor.');

        // Spaltenblock mit Währung hinter den Werten und Steuersatz in der Beschriftung (Versandhändler-Form).
        $columns = $service->analyzeText("RECHNUNG\nKundennr.:\nRechnungsnr.:\nAuftragsnr.:\nLieferdatum/Rechnungsdatum:\n000111222\n300400500\n600700800\n04.08.2025\nNettowarenwert\nMwSt. entspricht 19,0%\nRechnungsbetrag\nZahlart: Karte\nGesamtpreis\n100,00 EUR\n19,00 EUR\n119,00 EUR");
        self::assertSame('300400500', $columns['number']);
        self::assertSame('2025-08-04', $columns['issued_on']);
        self::assertSame('119.00', $columns['gross']);
        self::assertSame('100.00', $columns['net']);
        self::assertSame('19.00', $columns['tax']);

        // Währungszeile zwischen den Werten und „Gesamtwert“ (Bürohändler-Form).
        $office = $service->analyzeText("RECHNUNG\nRechnung Nr.\nDatum\nKunden-Nr.\n: 770011223344\n: 21.05.2025\n: 5544332\nNetto\n20,00\nMwSt 19%\n3,80\nSteuerfrei\nEUR\nGesamtwert\n23,80");
        self::assertSame('770011223344', $office['number']);
        self::assertSame('23.80', $office['gross']);
        self::assertSame('20.00', $office['net']);
        self::assertSame('3.80', $office['tax']);

        // Kennung direkt hinter dem Titel; ein Datum hinter dem Titel ist keine Nummer.
        self::assertSame('2025-0815', $service->analyzeText("Rechnung 2025-0815\nGesamtbetrag 10,00")['number']);
        self::assertNull($service->analyzeText("Rechnung 13.01.2025\nGesamtbetrag 10,00")['number']);
        self::assertNull($service->analyzeText("Rechnung 2025-0815\nGesamtbetrag 10,00")['number_hint']);
    }
}
