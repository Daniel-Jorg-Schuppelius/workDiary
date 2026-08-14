<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper};
use CommonToolkit\Parsers\XLSXDocumentParser;
use PDFToolkit\Registries\PDFReaderRegistry;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Liest PDF-, Word- und Excel-Rechnungen und erzeugt ausschließlich prüfbare
 * Feldvorschläge. Makros/Formeln werden nie ausgeführt; das unveränderte
 * Quelldokument bleibt die fachliche Quelle.
 */
class InvoicePdfImportService {
    /**
     * @return array<string, mixed>
     */
    public function extract(string $path, string $extension = 'pdf'): array {
        $extension = mb_strtolower($extension);
        [$text, $reader, $ocrUsed] = match ($extension) {
            'pdf' => $this->pdfText($path),
            'docx' => [$this->docxText($path), 'docx', false],
            'xlsx' => [$this->xlsxText($path), 'xlsx-toolkit', false],
            'doc' => [$this->legacyWordText($path), 'catdoc', false],
            'xls' => [$this->legacyExcelText($path), 'phpspreadsheet', false],
            default => ['', null, false],
        };

        $result = $this->analyzeText($text);
        $result['reader'] = $reader;
        $result['ocr_used'] = $ocrUsed;
        $result['text_length'] = mb_strlen($text);
        $result['source_format'] = $extension;

        return $result;
    }

    /** @return array{0: string, 1: ?string, 2: bool} */
    private function pdfText(string $path): array {
        $document = PDFReaderRegistry::getInstance()->extractText($path, [
            'language' => 'deu+eng',
            'qualityCheck' => true,
        ]);

        return [$document->getTextOrDefault(), $document->reader?->value, $document->isScanned];
    }

    /** DOCX wird als OOXML-ZIP gelesen; eingebettete Objekte und Makros bleiben unberührt. */
    private function docxText(string $path): string {
        $zip = $this->openOfficeArchive($path, 'word/document.xml');

        try {
            $parts = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (preg_match('#^word/(?:document|header\d+|footer\d+)\.xml$#', $name) !== 1) {
                    continue;
                }
                $xml = $zip->getFromIndex($index);
                if (! is_string($xml) || str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
                    continue;
                }
                $dom = new \DOMDocument;
                $previous = libxml_use_internal_errors(true);
                try {
                    if ($dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                        $xpath = new \DOMXPath($dom);
                        $paragraphs = $xpath->query('//*[local-name()="p"]');
                        if ($paragraphs !== false) {
                            foreach ($paragraphs as $paragraph) {
                                if (! $paragraph instanceof \DOMNode) {
                                    continue;
                                }
                                $value = trim((string) $paragraph->textContent);
                                if ($value !== '') {
                                    $parts[] = $value;
                                }
                            }
                        }
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }

            return implode("\n", $parts);
        } finally {
            $zip->close();
        }
    }

    /** XLSX wird über den eigenen CommonToolkit-Parser ohne Formelberechnung gelesen. */
    private function xlsxText(string $path): string {
        $this->assertOfficeArchive($path, 'xl/workbook.xml');
        $document = XLSXDocumentParser::fromFile($path, hasHeader: false);
        $lines = [];
        foreach ($document->getSheets() as $sheet) {
            foreach ($sheet->getRows() as $row) {
                $values = [];
                foreach ($row->getCells() as $cell) {
                    $value = $cell->getValue();
                    $values[] = match (true) {
                        $value instanceof \DateTimeInterface => $value->format('d.m.Y'),
                        is_float($value), is_int($value) => NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true),
                        default => trim($cell->getStringValue()),
                    };
                }
                $line = trim(implode(' ', array_filter($values, static fn(string $value): bool => $value !== '')));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    /** Legacy-DOC: rein lesender Konverter, falls catdoc auf dem Host verfügbar ist. */
    private function legacyWordText(string $path): string {
        $process = new Process(['catdoc', $path]);
        $process->setTimeout(30);
        try {
            $process->mustRun();
        } catch (Throwable) {
            return '';
        }

        return $process->getOutput();
    }

    /** Legacy-XLS: PhpSpreadsheet liest Zellwerte, Formeln werden nicht berechnet. */
    private function legacyExcelText(string $path): string {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return '';
        }

        $lines = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, false, false, false) as $row) {
                $line = trim(implode(' ', array_map(static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $row)));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        $spreadsheet->disconnectWorksheets();

        return implode("\n", $lines);
    }

    private function assertOfficeArchive(string $path, string $requiredEntry): void {
        $zip = $this->openOfficeArchive($path, $requiredEntry);
        $zip->close();
    }

    /** ZIP-Bomb-/Format-Guard vor jedem OOXML-Parser. */
    private function openOfficeArchive(string $path, string $requiredEntry): ZipArchive {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Invalid Office Open XML document.');
        }
        if ($zip->locateName($requiredEntry) === false || $zip->numFiles > 5000) {
            $zip->close();
            throw new \RuntimeException('Invalid Office Open XML document.');
        }

        $uncompressed = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $uncompressed += (int) ($stat['size'] ?? 0);
            if ($uncompressed > 64 * 1024 * 1024) {
                $zip->close();
                throw new \RuntimeException('Office Open XML document exceeds extraction limit.');
            }
        }

        return $zip;
    }

    /**
     * Deterministische Heuristik für deutsch-/englischsprachige Rechnungen.
     * Öffentliche Methode, damit die Felder unabhängig von System-OCR testbar
     * bleiben.
     *
     * @return array{
     *   number: ?string, issued_on: ?string, due_on: ?string,
     *   currency: string, net: ?string, tax: ?string, gross: ?string,
     *   tax_rate: ?string, buyer_reference: ?string,
     *   confidence: int, warnings: list<string>
     * }
     */
    public function analyzeText(string $text): array {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x{00A0}\t]+/u', ' ', $text) ?? $text;

        $number = $this->labeledValue($text, [
            'Rechnungs(?:nummer|nr\.?|\s*Nr\.?)', 'Belegnummer',
            'Invoice\s*(?:Number|No\.?)',
        ], '[A-Z0-9][A-Z0-9._\/-]{1,63}');
        $issuedOn = $this->labeledDate($text, ['Rechnungsdatum', 'Belegdatum', 'Invoice\s*date']);
        $dueOn = $this->labeledDate($text, ['F.llig(?:keit(?:sdatum)?|\s*am)', 'Zahlbar\s*bis', 'Due\s*date']);
        $buyerReference = $this->labeledValue($text, [
            'Leitweg-?ID', 'Käuferreferenz', 'Bestellreferenz', 'Buyer\s*reference',
        ], '[A-Z0-9][A-Z0-9._\/-]{2,99}');

        $net = $this->labeledAmount($text, ['Nettobetrag', 'Netto(?:summe)?', 'Net\s*amount', 'Subtotal']);
        $tax = $this->labeledAmount($text, ['Umsatzsteuer', 'Mehrwertsteuer', 'MwSt\.?', 'USt\.?(?!-Id)', 'VAT']);
        $gross = $this->labeledAmount($text, [
            'Rechnungsbetrag', 'Gesamtbetrag', 'Bruttobetrag', 'Zahlbetrag',
            'Grand\s*total', 'Amount\s*due', 'Total',
        ], last: true);

        if ($net === null && $gross !== null && $tax !== null) {
            $net = $this->decimal((float) $gross - (float) $tax);
        }
        if ($tax === null && $gross !== null && $net !== null) {
            $tax = $this->decimal((float) $gross - (float) $net);
        }

        $taxRate = $this->taxRate($text);
        if ($taxRate === null && $net !== null && $tax !== null && (float) $net > 0.0) {
            $taxRate = $this->decimal(((float) $tax / (float) $net) * 100);
        }

        $currency = match (true) {
            preg_match('/(?:\bCHF\b|Fr\.)/iu', $text) === 1 => 'CHF',
            preg_match('/(?:\bUSD\b|US\$)/iu', $text) === 1 => 'USD',
            preg_match('/(?:\bGBP\b|£)/u', $text) === 1 => 'GBP',
            default => 'EUR',
        };

        $warnings = [];
        foreach ([
            'number' => $number,
            'issued_on' => $issuedOn,
            'net' => $net,
        ] as $field => $value) {
            if ($value === null) {
                $warnings[] = 'missing_' . $field;
            }
        }
        if ($net !== null && $tax !== null && $gross !== null
            && abs(((float) $net + (float) $tax) - (float) $gross) > 0.02) {
            $warnings[] = 'totals_mismatch';
        }

        $found = count(array_filter([$number, $issuedOn, $dueOn, $net, $tax, $gross, $taxRate, $buyerReference], static fn(mixed $value): bool => $value !== null));

        return [
            'number' => $number,
            'issued_on' => $issuedOn,
            'due_on' => $dueOn,
            'currency' => $currency,
            'net' => $net,
            'tax' => $tax,
            'gross' => $gross,
            'tax_rate' => $taxRate,
            'buyer_reference' => $buyerReference,
            'confidence' => min(100, $found * 12),
            'warnings' => $warnings,
        ];
    }

    /** @param list<string> $labels */
    private function labeledValue(string $text, array $labels, string $valuePattern): ?string {
        $pattern = '/(?:' . implode('|', $labels) . ')\s*[:#]?\s*(' . $valuePattern . ')/iu';
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return mb_substr(trim($matches[1]), 0, 100);
    }

    /** @param list<string> $labels */
    private function labeledDate(string $text, array $labels): ?string {
        $raw = $this->labeledValue($text, $labels, '(?:\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}|\d{4}-\d{2}-\d{2})');
        if ($raw === null) {
            return null;
        }

        return DateHelper::parseWithCountryPreference($raw, CountryCode::Germany)?->format('Y-m-d');
    }

    /** @param list<string> $labels */
    private function labeledAmount(string $text, array $labels, bool $last = false): ?string {
        $pattern = '/(?:' . implode('|', $labels) . ')\s*[:]?\s*(?:\d{1,2}(?:[,.]\d{1,2})?\s*%\s*)?(?:EUR|USD|CHF|GBP|€|\$|£)?\s*([+-]?[\d .]+(?:,\d{2}|\.\d{2}))\s*(?:EUR|USD|CHF|GBP|€|\$|£)?/iu';
        if (preg_match_all($pattern, $text, $matches) < 1) {
            return null;
        }

        $values = $matches[1];
        $raw = $last ? end($values) : reset($values);
        $normalized = NumberHelper::normalizeDecimalStringOrNull((string) $raw, CountryCode::Germany);

        return $normalized !== null ? $this->decimal((float) $normalized) : null;
    }

    private function taxRate(string $text): ?string {
        if (preg_match('/(?:Umsatzsteuer|Mehrwertsteuer|MwSt\.?|USt\.?|VAT)\s*(?:\([A-Z]\))?\s*[:]?\s*(\d{1,2}(?:[,.]\d{1,2})?)\s*%/iu', $text, $matches) !== 1) {
            return null;
        }

        $normalized = NumberHelper::normalizeDecimalStringOrNull($matches[1], CountryCode::Germany);

        return $normalized !== null ? $this->decimal((float) $normalized) : null;
    }

    private function decimal(float $value): string {
        return number_format(max(0.0, $value), 2, '.', '');
    }
}
