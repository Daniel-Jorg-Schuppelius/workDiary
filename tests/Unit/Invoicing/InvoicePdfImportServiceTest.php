<?php

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
}
