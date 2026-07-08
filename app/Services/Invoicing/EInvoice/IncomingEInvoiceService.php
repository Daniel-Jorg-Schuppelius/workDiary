<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use ERechnungToolkit\Entities\Document as EInvoiceDocument;
use ERechnungToolkit\Parsers\{ERechnungParser, ZugferdPdfParser};
use Throwable;

/**
 * Eingangs-E-Rechnung (Nachtrag 045b): parst XRechnung/ZUGFeRD über das
 * php-erechnung-toolkit — XML direkt (ERechnungParser, mit eingebauter
 * UBL/CII-Formaterkennung), PDF über den eingebetteten ZUGFeRD-Anhang
 * (ZugferdPdfParser, braucht das pdf-toolkit). Die Rechnung wird NICHT als
 * lokale Invoice übernommen (Rechnungshoheit beim externen Programm) —
 * nur angezeigt und als Document (Typ Rechnung) im DMS abgelegt.
 */
class IncomingEInvoiceService {
    /**
     * Versucht, Dateiinhalt als E-Rechnung zu parsen. `null`, wenn es keine
     * (lesbare) E-Rechnung ist — Aufrufer behandeln das als „normale Datei".
     */
    public function parse(string $contents, ?string $mime = null, ?string $path = null): ?EInvoiceDocument {
        $isPdf = str_starts_with($contents, '%PDF')
            || ($mime !== null && str_contains($mime, 'pdf'));

        if ($isPdf) {
            return $this->parsePdf($contents, $path);
        }

        if (! str_contains(substr($contents, 0, 512), '<')) {
            return null; // offensichtlich kein XML
        }

        try {
            return (new ERechnungParser)->parse($contents);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Rohes Rechnungs-XML des Eingangs (MVP-166): XML-Uploads direkt,
     * ZUGFeRD-PDFs über die Toolkit-Extraktion (null = nicht extrahierbar).
     */
    public function extractXml(string $contents, ?string $mime = null, ?string $path = null): ?string {
        $isPdf = str_starts_with($contents, '%PDF') || ($mime !== null && str_contains($mime, 'pdf'));
        if (! $isPdf) {
            return str_contains(substr($contents, 0, 512), '<') ? $contents : null;
        }
        if ($path === null) {
            return null;
        }

        try {
            return (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->extractXml($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Eingangs-Validierung (MVP-166): UBL-XSD (nur wenn das Schema das
     * Wurzelelement kennt — CII wird nur von KoSIT geprüft) + KoSIT
     * (XSD/EN-16931/CIUS). Verfügbarkeit wird transparent ausgewiesen.
     *
     * @return array{schema_checked: bool, schema_errors: array<int, string>, kosit_available: bool, kosit_valid: bool|null, kosit_errors: array<int, string>}
     */
    public function validateXml(string $xml): array {
        $result = [
            'schema_checked' => false,
            'schema_errors' => [],
            'kosit_available' => false,
            'kosit_valid' => null,
            'kosit_errors' => [],
        ];

        $schema = new \ERechnungToolkit\Validators\UblSchemaValidator;
        $root = null;
        try {
            $dom = new \DOMDocument;
            if (@$dom->loadXML($xml)) {
                $root = $dom->documentElement?->localName;
            }
        } catch (Throwable) {
        }
        if ($schema->isAvailable() && $root !== null && $schema->supports($root)) {
            $result['schema_checked'] = true;
            $result['schema_errors'] = $schema->validate($xml);
        }

        $kosit = new \ERechnungToolkit\Validators\KositValidator;
        $result['kosit_available'] = $kosit->isAvailable();
        if ($result['kosit_available']) {
            $report = $kosit->validate($xml);
            $result['kosit_valid'] = $report->isAccepted();
            $result['kosit_errors'] = array_map('strval', $report->getErrors());
        }

        return $result;
    }

    /**
     * Kernfelder für Anzeige/Flash — die Detailseite parst das Original
     * bei jedem Aufruf erneut (kein eigenes Schema, Quelle bleibt die Datei).
     *
     * @return array{number: string, issue_date: ?string, due_date: ?string, seller: ?string, seller_vat: ?string, currency: string, net: ?float, tax: ?float, gross: ?float, profile: string, lines: int}
     */
    public function summary(EInvoiceDocument $document): array {
        return [
            'number' => $document->getId(),
            'issue_date' => $document->getIssueDate()->format('Y-m-d'),
            'due_date' => $document->getDueDate()?->format('Y-m-d'),
            'seller' => $document->getSeller()->getName(),
            'seller_vat' => $document->getSeller()->getVatId(),
            'currency' => $document->getCurrency()->value,
            'net' => $document->getNetAmount(),
            'tax' => $document->getTaxAmount(),
            'gross' => $document->getGrossAmount(),
            'profile' => $document->getProfile()->label(),
            'lines' => $document->countLines(),
        ];
    }

    private function parsePdf(string $contents, ?string $path): ?EInvoiceDocument {
        $parser = new ZugferdPdfParser;
        if (! $parser->isAvailable()) {
            return null;
        }

        // Der PDF-Parser arbeitet dateibasiert; ohne bekannten Pfad kurz puffern.
        $tempPath = null;
        if ($path === null || ! is_file($path)) {
            $tempPath = tempnam(sys_get_temp_dir(), 'einvoice-');
            if ($tempPath === false) {
                return null;
            }
            file_put_contents($tempPath, $contents);
            $path = $tempPath;
        }

        try {
            if (! $parser->isZugferdPdf($path)) {
                return null;
            }

            return $parser->parseFile($path);
        } catch (Throwable) {
            return null;
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
        }
    }
}
