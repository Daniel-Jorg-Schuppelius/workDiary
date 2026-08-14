<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePdfImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper};
use CommonToolkit\Parsers\XLSXDocumentParser;
use ERechnungToolkit\Entities\Document as EInvoiceDocument;
use ERechnungToolkit\Enums\{TaxCategory, UnitCode};
use PDFToolkit\Registries\PDFReaderRegistry;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Liest PDF-, Word-, Excel- und XML-Rechnungen und erzeugt ausschließlich
 * prüfbare Feldvorschläge. Strukturierte E-Rechnungen (XRechnung-XML,
 * ZUGFeRD-PDF) gewinnen immer vor der Text-Heuristik (Feature-088-Leitplanke)
 * und liefern echte Positionen. Makros/Formeln werden nie ausgeführt; das
 * unveränderte Quelldokument bleibt die fachliche Quelle.
 */
class InvoicePdfImportService {
    /** Capability-Key des KI-Fallbacks (Registrierung: config/ai.php). */
    public const AI_CAPABILITY = 'invoicing.document_extraction';

    /**
     * @return array<string, mixed>
     */
    public function extract(string $path, string $extension = 'pdf', ?string $mime = null, ?\App\Models\Organization $organization = null): array {
        $extension = mb_strtolower($extension);

        $structured = $this->structuredExtract($path, $extension, $mime);
        if ($structured !== null) {
            return $structured;
        }

        [$text, $reader, $ocrUsed, $rows, $alignedText] = match ($extension) {
            'pdf' => $this->pdfContent($path),
            'docx' => $this->docxContent($path),
            'xlsx' => $this->xlsxContent($path),
            'doc' => [$this->legacyWordText($path), 'catdoc', false, [], null],
            'xls' => $this->legacyExcelContent($path),
            default => ['', null, false, [], null],
        };

        $result = $this->analyzeText($text);
        $result['reader'] = $reader;
        $result['ocr_used'] = $ocrUsed;
        $result['text_length'] = mb_strlen($text);
        $result['source_format'] = $extension;
        $this->augmentWithLines($result, $rows, $alignedText);
        $this->augmentWithPaymentData($result, $text);
        if ($organization !== null) {
            $this->augmentWithAiFallback($result, $text, $organization);
        }

        return $result;
    }

    /**
     * KI-Fallback (Feature 088): nur wenn die Regel-Heuristik Kernfelder
     * schuldig bleibt, nie für strukturierte E-Rechnungen (die kommen hier
     * nie an — XML gewinnt vorher). Füllt ausschließlich LEERE Felder mit
     * konfidenzgeprüften, formatvalidierten Werten; ohne Provider/Modul
     * verhält sich der Import exakt wie ohne KI.
     *
     * @param  array<string, mixed>  $result
     */
    private function augmentWithAiFallback(array &$result, string $text, \App\Models\Organization $organization): void {
        $missing = array_values(array_filter(
            ['number', 'issued_on', 'due_on', 'net', 'tax', 'gross', 'tax_rate', 'buyer_reference'],
            static fn(string $field): bool => ($result[$field] ?? null) === null,
        ));
        if ($missing === [] || trim($text) === '') {
            return;
        }

        $schemaCatalog = [
            'number' => 'Rechnungsnummer (exakt wie im Text)',
            'issued_on' => 'Rechnungsdatum im Format JJJJ-MM-TT',
            'due_on' => 'Fälligkeitsdatum im Format JJJJ-MM-TT',
            'net' => 'Nettobetrag als Dezimalzahl mit Punkt (z. B. 1000.00)',
            'tax' => 'Umsatzsteuerbetrag als Dezimalzahl mit Punkt',
            'gross' => 'Bruttobetrag als Dezimalzahl mit Punkt',
            'tax_rate' => 'Umsatzsteuersatz in Prozent als Dezimalzahl (z. B. 19.00)',
            'buyer_reference' => 'Leitweg-ID oder Käuferreferenz',
        ];
        $schema = array_intersect_key($schemaCatalog, array_fill_keys($missing, true));

        try {
            $invocation = app(\App\Services\Ai\AiInvocationService::class)->invoke(
                $organization,
                self::AI_CAPABILITY,
                new \App\Services\Ai\Dto\ExtractRequest(mb_substr($text, 0, 6000), $schema),
            );
        } catch (Throwable) {
            return; // Kein Provider/Budget/Modul: Verhalten exakt wie ohne KI.
        }

        $extraction = $invocation->result;
        if (! $extraction instanceof \App\Services\Ai\Dto\AiExtractionResult) {
            return;
        }

        $filled = [];
        foreach ($missing as $field) {
            $value = $extraction->confidentValue($field);
            if ($value === null) {
                continue;
            }
            $normalized = $this->normalizeAiValue($field, $value);
            if ($normalized === null) {
                continue;
            }
            $result[$field] = $normalized;
            $filled[$field] = $extraction->confidence[$field] ?? 0;
            $result['warnings'] = array_values(array_diff((array) $result['warnings'], ['missing_' . $field]));
        }

        if ($filled !== []) {
            $result['ai'] = [
                'used' => true,
                'capability' => self::AI_CAPABILITY,
                'provider' => $invocation->provider->value,
                'fallback_used' => $invocation->fallbackUsed,
                'from_cache' => $invocation->fromCache,
                'fields' => $filled,
            ];
            $result['warnings'][] = 'ai_fields_filled';
        }
    }

    /** Formatvalidierung je KI-Feld — was nicht parsebar ist, wird verworfen. */
    private function normalizeAiValue(string $field, string $value): ?string {
        return match ($field) {
            'issued_on', 'due_on' => DateHelper::parseWithCountryPreference($value, CountryCode::Germany)?->format('Y-m-d'),
            'net', 'tax', 'gross' => ($n = NumberHelper::normalizeDecimalStringOrNull($value, CountryCode::Germany)) !== null
                ? $this->decimal((float) $n)
                : null,
            'tax_rate' => ($n = NumberHelper::normalizeDecimalStringOrNull($value, CountryCode::Germany)) !== null && (float) $n <= 100.0
                ? $this->decimal((float) $n)
                : null,
            default => mb_substr($value, 0, 100),
        };
    }

    /**
     * Positions-Vorschläge aus Tabellenzeilen bzw. spaltentreuem Text —
     * nur mit Summen-Gegenprobe gegen das erkannte Netto (2 ct Toleranz);
     * sonst bleibt die Sammelzeile der sichere Fallback.
     *
     * @param  array<string, mixed>  $result
     * @param  list<list<mixed>>  $rows
     */
    private function augmentWithLines(array &$result, array $rows, ?string $alignedText): void {
        $detector = new InvoiceLineDetector;
        $lines = $rows !== [] ? $detector->detectFromRows($rows) : null;
        if ($lines === null && $alignedText !== null && trim($alignedText) !== '') {
            $lines = $detector->detectFromAlignedText($alignedText);
        }
        if ($lines === null) {
            return;
        }

        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += (float) $line['amount'];
        }
        if ($result['net'] !== null && abs($sum - (float) $result['net']) > 0.02) {
            $result['warnings'][] = 'line_items_rejected_totals';

            return;
        }

        foreach ($lines as &$line) {
            $line['tax_rate'] ??= $result['tax_rate'];
            unset($line['amount']);
        }
        unset($line);

        $result['lines'] = $lines;
        if ($result['net'] === null) {
            $result['net'] = number_format($sum, 2, '.', '');
            $result['warnings'] = array_values(array_diff($result['warnings'], ['missing_net']));
        }
    }

    /**
     * Zahlungs-/Stammdaten aus dem Text: IBAN/BIC (Toolkit-Extraktion),
     * USt-IdNr., Skonto-Klausel und Zahlungsziel — als Vorschlag bzw. zur
     * Gegenprobe mit den Org-Stammdaten im Controller.
     *
     * @param  array<string, mixed>  $result
     */
    private function augmentWithPaymentData(array &$result, string $text): void {
        $iban = \CommonToolkit\Helper\Data\BankHelper::extractIBAN($text, spaceTolerant: true);
        $bic = null;
        if ($iban !== null) {
            try {
                $bic = \CommonToolkit\Helper\Data\BankHelper::bicFromIBAN($iban);
            } catch (Throwable) {
                $bic = null;
            }
        }
        $result['payment'] = ['iban' => $iban, 'bic' => $bic];

        $vat = $this->labeledValue($text, [
            'USt[-\s]?IdNr\.?', 'USt[-\s]?ID', 'Umsatzsteuer-?Identifikationsnummer', 'VAT\s*(?:ID|number|no\.?)',
        ], '[A-Z]{2}\s?[0-9A-Z][0-9A-Z ]{5,13}');
        if ($vat !== null) {
            $vat = \CommonToolkit\Helper\Data\VatNumberHelper::normalize($vat);
            $result['seller_vat'] = \CommonToolkit\Helper\Data\VatNumberHelper::isVatId($vat) ? $vat : null;
        } else {
            $result['seller_vat'] = null;
        }

        $percentRaw = null;
        $daysRaw = null;
        if (preg_match('/(\d{1,2}(?:[.,]\d{1,2})?)\s*%\s*Skonto[^.\n]{0,60}?(\d{1,3})\s*Tag/iu', $text, $m) === 1) {
            [$percentRaw, $daysRaw] = [$m[1], $m[2]];
        } elseif (preg_match('/Skonto[^.\n]{0,60}?(\d{1,3})\s*Tag[^.\n]{0,40}?(\d{1,2}(?:[.,]\d{1,2})?)\s*%/iu', $text, $m) === 1) {
            [$percentRaw, $daysRaw] = [$m[2], $m[1]];
        }
        if ($percentRaw !== null) {
            $percent = (float) str_replace(',', '.', $percentRaw);
            $days = (int) (string) $daysRaw;
            if ($percent > 0.0 && $percent < 100.0 && $days > 0 && $days <= 365) {
                $result['skonto'] = ['percent' => number_format($percent, 2, '.', ''), 'days' => $days];
            }
        }

        if (preg_match('/(?:zahlbar|Zahlung)\s+(?:innerhalb|binnen)\s+(?:von\s+)?(\d{1,3})\s*Tagen|Zahlungsziel\s*:?\s*(\d{1,3})\s*Tage/iu', $text, $terms) === 1) {
            $days = (int) ($terms[1] !== '' ? $terms[1] : $terms[2]);
            if ($days > 0 && $days <= 365) {
                $result['payment_terms_days'] = $days;
            }
        }
    }

    /**
     * Strukturierter Zweig: XML direkt bzw. ZUGFeRD-XML aus der PDF — bei
     * Erfolg entfällt die Heuristik komplett (null = keine E-Rechnung).
     *
     * @return array<string, mixed>|null
     */
    private function structuredExtract(string $path, string $extension, ?string $mime): ?array {
        if (! in_array($extension, ['pdf', 'xml'], true)) {
            return null;
        }

        $incoming = new IncomingEInvoiceService;
        $contents = (string) file_get_contents($path);
        $document = $incoming->parse($contents, $mime, $path);
        if ($document === null) {
            return null;
        }

        $xml = $incoming->extractXml($contents, $mime, $path);

        return $this->mapStructured(
            $document,
            $extension,
            $xml !== null ? $incoming->validateXml($xml) : null,
        );
    }

    /**
     * Toolkit-Dokument → einheitliches Extraktionsergebnis inkl. echter
     * Positionen. Öffentlich, damit das Mapping ohne Datei testbar bleibt.
     *
     * @param  array<string, mixed>|null  $validation
     * @return array<string, mixed>
     */
    public function mapStructured(EInvoiceDocument $document, string $extension = 'xml', ?array $validation = null): array {
        $lines = [];
        $position = 0;
        foreach ($document->getLines() as $line) {
            $position++;
            $unitPrice = $line->getUnitPrice()->withScale(4);
            $netAmount = $line->getNetAmount()->withScale(2);
            // BR-24-Logik rückwärts: Differenz Menge × Preis − Zeilennetto ist
            // der Positionsrabatt — unabhängig davon, ob der Parser die
            // Line-Allowance schon liefert.
            $discount = $unitPrice->times($line->getQuantity())->withScale(2)->minus($netAmount);
            $description = trim($line->getItemName());
            $detail = trim((string) $line->getItemDescription());
            if ($detail !== '' && $detail !== $description) {
                $description = $description !== '' ? $description . "\n" . $detail : $detail;
            }

            $lines[] = [
                'position' => $position,
                'description' => $description !== '' ? $description : (string) __('invoicing.service'),
                'quantity' => number_format($line->getQuantity(), 3, '.', ''),
                'unit' => $this->unitLabel($line->getUnitCode()),
                'unit_price' => $unitPrice->getAmount(),
                'tax_rate' => number_format($line->getTaxPercent(), 2, '.', ''),
                'tax_category' => $line->getTaxCategory()->value,
                'discount_amount' => $discount->isPositive() ? $discount->getAmount() : null,
            ];
        }

        // Kopf-Steuersatz: Subtotal mit größter Bemessungsgrundlage (die
        // Positionen behalten ihre eigenen Sätze — Mischsteuersätze bleiben).
        $taxRate = $lines[0]['tax_rate'] ?? null;
        $reverseCharge = false;
        $dominant = null;
        foreach ($document->getTaxTotal()?->getSubtotals() ?? [] as $subtotal) {
            if ($dominant === null || (float) $subtotal->getTaxableAmount()->getAmount() > (float) $dominant->getTaxableAmount()->getAmount()) {
                $dominant = $subtotal;
            }
        }
        if ($dominant !== null) {
            $taxRate = number_format($dominant->getPercent(), 2, '.', '');
            $reverseCharge = $dominant->getCategory() === TaxCategory::REVERSE_CHARGE;
        }

        $terms = $document->getPaymentTerms();
        $skonto = null;
        if ($terms !== null && ($terms->getDiscountPercent() ?? 0.0) > 0.0 && (int) ($terms->getDiscountDays() ?? 0) > 0) {
            $skonto = [
                'percent' => number_format((float) $terms->getDiscountPercent(), 2, '.', ''),
                'days' => (int) $terms->getDiscountDays(),
            ];
        } elseif (preg_match('/#SKONTO#TAGE=(\d+)#PROZENT=(\d+(?:[.,]\d+)?)#/u', (string) $terms?->getNote(), $skontoMatch) === 1) {
            // BR-DE-18-Note (XRechnung-CIUS): der Parser liefert Skonto bisher
            // nur als Freitext — typisierte Felder gewinnen, sobald vorhanden.
            $skonto = [
                'percent' => number_format((float) str_replace(',', '.', $skontoMatch[2]), 2, '.', ''),
                'days' => (int) $skontoMatch[1],
            ];
        }

        $monetary = $document->getMonetaryTotal();
        $documentDiscount = $monetary !== null && $monetary->getAllowanceTotalAmount()->isPositive()
            ? $monetary->getAllowanceTotalAmount()->withScale(2)->getAmount()
            : null;

        $warnings = [];
        if ($document->getInvoiceType()->isCredit()) {
            $warnings[] = 'credit_note_source';
        }
        if ($monetary !== null && $monetary->getPrepaidAmount()->isPositive()) {
            $warnings[] = 'prepaid_ignored';
        }
        if ($monetary !== null && $monetary->getChargeTotalAmount()->isPositive()) {
            $warnings[] = 'charges_present';
        }
        if (is_array($validation) && ($validation['kosit_valid'] ?? null) === false) {
            $warnings[] = 'kosit_invalid';
        }

        return [
            'structured' => true,
            'profile' => $document->getProfile()->label(),
            'number' => $document->getId(),
            'issued_on' => $document->getIssueDate()->format('Y-m-d'),
            'due_on' => $document->getDueDate()?->format('Y-m-d'),
            'service_date' => $document->getDeliveryDate()?->format('Y-m-d'),
            'currency' => $document->getCurrency()->value,
            'net' => $document->getNetAmount()->withScale(2)->getAmount(),
            'tax' => $document->getTaxAmount()->withScale(2)->getAmount(),
            'gross' => $document->getGrossAmount()->withScale(2)->getAmount(),
            'tax_rate' => $taxRate,
            'is_reverse_charge' => $reverseCharge,
            'buyer_reference' => $document->getBuyerReference(),
            'order_reference' => $document->getOrderReference(),
            'seller' => [
                'name' => $document->getSeller()->getName(),
                'vat_id' => $document->getSeller()->getVatId(),
            ],
            'payment' => [
                'iban' => $document->getSeller()->getIban(),
                'bic' => $document->getSeller()->getBic(),
            ],
            'skonto' => $skonto,
            'payment_terms_days' => $terms?->getNetPaymentDays(),
            'document_discount' => $documentDiscount,
            'lines' => $lines,
            'validation' => $validation,
            'confidence' => 100,
            'warnings' => $warnings,
            'reader' => 'erechnung-toolkit',
            'ocr_used' => false,
            'text_length' => 0,
            'source_format' => $extension === 'pdf' ? 'zugferd_pdf' : 'xrechnung_xml',
        ];
    }

    /** Rückrichtung des UNIT_CODES-Mappings im XRechnungGenerator. */
    private function unitLabel(UnitCode $code): string {
        return match ($code) {
            UnitCode::HOUR => 'Std.',
            UnitCode::DAY => 'Tag(e)',
            UnitCode::PIECE, UnitCode::UNIT_H87, UnitCode::UNIT => 'Stk.',
            UnitCode::LUMP_SUM, UnitCode::JOB => 'pauschal',
            UnitCode::KILOMETRE => 'km',
            UnitCode::KILOGRAM => 'kg',
            UnitCode::LITRE => 'l',
            UnitCode::SQUARE_METRE => 'm²',
            UnitCode::METRE => 'm',
            UnitCode::MINUTE => 'Min.',
            UnitCode::MONTH => 'Monat(e)',
            default => $code->value,
        };
    }

    /** @return array{0: string, 1: ?string, 2: bool, 3: list<list<mixed>>, 4: ?string} */
    private function pdfContent(string $path): array {
        $document = PDFReaderRegistry::getInstance()->extractText($path, [
            'language' => 'deu+eng',
            'qualityCheck' => true,
        ]);
        $text = $document->getTextOrDefault();
        $ocrUsed = $document->isScanned;

        // Spaltentreuer Zweittext (bbox-basiert) als Input der Positions-
        // erkennung — Fehlschläge sind kein Importfehler, nur weniger Komfort.
        $aligned = null;
        try {
            $provider = new \PDFToolkit\Helper\PDFTextProvider($path);
            $aligned = $ocrUsed ? $provider->ocrRowAlignedText() : $provider->rowAlignedText();
        } catch (Throwable) {
            $aligned = null;
        }

        return [$text, $document->reader?->value, $ocrUsed, [], $aligned];
    }

    /**
     * DOCX wird als OOXML-ZIP gelesen; eingebettete Objekte und Makros bleiben
     * unberührt. Tabellen (`w:tbl`) werden zusätzlich zellenweise gesammelt.
     *
     * @return array{0: string, 1: ?string, 2: bool, 3: list<list<mixed>>, 4: ?string}
     */
    private function docxContent(string $path): array {
        $zip = $this->openOfficeArchive($path, 'word/document.xml');

        try {
            $parts = [];
            $rows = [];
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
                        $tableRows = $xpath->query('//*[local-name()="tbl"][not(ancestor::*[local-name()="tbl"])]/*[local-name()="tr"]');
                        if ($tableRows !== false) {
                            foreach ($tableRows as $tableRow) {
                                if (! $tableRow instanceof \DOMElement) {
                                    continue;
                                }
                                $cells = [];
                                foreach ($tableRow->childNodes as $cell) {
                                    if ($cell instanceof \DOMElement && $cell->localName === 'tc') {
                                        $cells[] = trim((string) $cell->textContent);
                                    }
                                }
                                if ($cells !== []) {
                                    $rows[] = $cells;
                                }
                            }
                        }
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }

            return [implode("\n", $parts), 'docx', false, $rows, null];
        } finally {
            $zip->close();
        }
    }

    /**
     * XLSX wird über den eigenen CommonToolkit-Parser ohne Formelberechnung
     * gelesen — Fließtext für die Feld-Heuristik, Rohzellen für die
     * Positionserkennung.
     *
     * @return array{0: string, 1: ?string, 2: bool, 3: list<list<mixed>>, 4: ?string}
     */
    private function xlsxContent(string $path): array {
        $this->assertOfficeArchive($path, 'xl/workbook.xml');
        $document = XLSXDocumentParser::fromFile($path, hasHeader: false);
        $lines = [];
        $rows = [];
        foreach ($document->getSheets() as $sheet) {
            foreach ($sheet->getRows() as $row) {
                $values = [];
                $cells = [];
                foreach ($row->getCells() as $cell) {
                    $value = $cell->getValue();
                    $cells[] = $value instanceof \DateTimeInterface || is_float($value) || is_int($value)
                        ? $value
                        : trim($cell->getStringValue());
                    $values[] = match (true) {
                        $value instanceof \DateTimeInterface => $value->format('d.m.Y'),
                        is_float($value), is_int($value) => NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true),
                        default => trim($cell->getStringValue()),
                    };
                }
                if ($cells !== []) {
                    $rows[] = $cells;
                }
                $line = trim(implode(' ', array_filter($values, static fn(string $value): bool => $value !== '')));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return [implode("\n", $lines), 'xlsx-toolkit', false, $rows, null];
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

    /**
     * Legacy-XLS: PhpSpreadsheet liest Zellwerte, Formeln werden nicht berechnet.
     *
     * @return array{0: string, 1: ?string, 2: bool, 3: list<list<mixed>>, 4: ?string}
     */
    private function legacyExcelContent(string $path): array {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return ['', 'phpspreadsheet', false, [], null];
        }

        $lines = [];
        $rows = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, false, false, false) as $row) {
                $cells = array_map(static fn(mixed $value): mixed => is_scalar($value) || $value instanceof \DateTimeInterface ? $value : '', $row);
                if (array_filter($cells, static fn(mixed $cell): bool => $cell !== '') !== []) {
                    $rows[] = array_values($cells);
                }
                $line = trim(implode(' ', array_map(static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $row)));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        $spreadsheet->disconnectWorksheets();

        return [implode("\n", $lines), 'phpspreadsheet', false, $rows, null];
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
