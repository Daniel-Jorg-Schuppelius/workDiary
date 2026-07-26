<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EInvoiceValidationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Models\Invoice;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Validators\{KositValidator, UblSchemaValidator};

/**
 * Vollständige Ausgangs-Validierung (Feature 066, MVP-164): fachlicher
 * Preflight → UBL-2.1-XSD (immer verfügbar, pure PHP) → KoSIT
 * (EN-16931-Schematron + XRechnung-CIUS über den Toolkit-Validator;
 * braucht Java + Validator-JAR — ohne beides wird das transparent im
 * Bericht ausgewiesen, nie stillschweigend übersprungen).
 */
class EInvoiceValidationService {
    public function __construct(
        private readonly XRechnungGenerator $generator,
    ) {}

    /**
     * @return array{
     *   preflight_errors: array<int, string>, preflight_warnings: array<int, string>,
     *   xml_generated: bool,
     *   schema_errors: array<int, string>,
     *   kosit_available: bool, kosit_valid: bool|null,
     *   kosit_errors: array<int, string>, kosit_warnings: array<int, string>,
     *   consistency: array{checked: bool, zugferd_checked: bool, errors: array<int, string>},
     *   valid: bool
     * }
     */
    public function validate(Invoice $invoice): array {
        $preflight = $this->generator->preflight($invoice);
        $report = [
            'preflight_errors' => $preflight['errors'],
            'preflight_warnings' => $preflight['warnings'],
            'xml_generated' => false,
            'schema_errors' => [],
            'kosit_available' => false,
            'kosit_valid' => null,
            'kosit_errors' => [],
            'kosit_warnings' => [],
            'consistency' => ['checked' => false, 'zugferd_checked' => false, 'errors' => []],
            'valid' => false,
        ];

        if ($report['preflight_errors'] !== []) {
            return $report; // ohne fachliche Basis kein XML-Versuch
        }

        $xml = $this->generator->generate($invoice);
        $report['xml_generated'] = true;

        $schema = new UblSchemaValidator;
        if ($schema->isAvailable()) {
            $report['schema_errors'] = $schema->validate($xml);
        }

        $kosit = new KositValidator;
        $report['kosit_available'] = $kosit->isAvailable();
        if ($report['kosit_available']) {
            $result = $kosit->validate($xml);
            $report['kosit_valid'] = $result->isAccepted();
            $report['kosit_errors'] = array_map('strval', $result->getErrors());
            $report['kosit_warnings'] = array_map('strval', $result->getWarnings());
        }

        $report['consistency'] = $this->checkConsistency($invoice, $xml);

        $report['valid'] = $report['schema_errors'] === []
            && ($report['kosit_valid'] ?? true) !== false
            && $report['consistency']['errors'] === [];

        return $report;
    }

    /**
     * Reproduzierbarer XML-/PDF-Abgleich (MVP-164, Restpaket): das erzeugte
     * UBL wird zurückgeparst und centgenau gegen den Beleg geprüft; ist
     * ZUGFeRD verfügbar, wird zusätzlich das eingebettete CII aus dem
     * PDF/A-3 extrahiert und gegen DIESELBEN Summen verglichen — XRechnung,
     * ZUGFeRD und visuelle Darstellung dürfen nie voneinander abweichen.
     *
     * @return array{checked: bool, zugferd_checked: bool, errors: array<int, string>}
     */
    private function checkConsistency(Invoice $invoice, string $ublXml): array {
        $result = ['checked' => false, 'zugferd_checked' => false, 'errors' => []];

        try {
            $parsed = (new \ERechnungToolkit\Parsers\ERechnungParser)->parse($ublXml);
            $result['checked'] = true;
            $result['errors'] = [...$result['errors'], ...$this->compareTotals($invoice, $parsed, 'UBL')];
        } catch (\Throwable $e) {
            $result['errors'][] = (string) __('UBL-Rückparse fehlgeschlagen: :reason', ['reason' => $e->getMessage()]);
        }

        if ($this->generator->zugferdAvailable()) {
            $tempPath = null;
            try {
                // Identische visuelle Darstellung wie der Download (inkl.
                // Dokumentdesign-Komposition, Feature 076).
                $visualHtml = app(\App\Services\Invoicing\InvoicePdfRenderer::class)->composedHtml($invoice);
                $pdf = $this->generator->generateZugferdPdf($invoice, $visualHtml);
                if ($pdf !== null) {
                    $tempPath = tempnam(sys_get_temp_dir(), 'zugferd-check-');
                    if ($tempPath !== false) {
                        file_put_contents($tempPath, $pdf);
                        $cii = (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->parseFile($tempPath);
                        if ($cii !== null) {
                            $result['zugferd_checked'] = true;
                            $result['errors'] = [...$result['errors'], ...$this->compareTotals($invoice, $cii, 'ZUGFeRD/CII')];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $result['errors'][] = (string) __('ZUGFeRD-Abgleich fehlgeschlagen: :reason', ['reason' => $e->getMessage()]);
            } finally {
                if (is_string($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        return $result;
    }

    /** @return array<int, string> */
    private function compareTotals(Invoice $invoice, \ERechnungToolkit\Entities\Document $parsed, string $label): array {
        $errors = [];
        $currency = $parsed->getCurrency();
        $expected = [
            'net' => Money::of((string) $invoice->subtotal, $currency),
            'tax' => Money::of((string) $invoice->tax_amount, $currency),
            'gross' => Money::of((string) $invoice->total, $currency),
        ];
        $actual = [
            'net' => $parsed->getNetAmount(),
            'tax' => $parsed->getTaxAmount(),
            'gross' => $parsed->getGrossAmount(),
        ];
        // Money rechnet exakt — Abweichung heißt Abweichung, keine Toleranzschwelle.
        foreach ($expected as $key => $value) {
            if (!$actual[$key]->equals($value)) {
                $errors[] = (string) __(':format: :field weicht ab (Beleg :expected, XML :actual).', [
                    'format' => $label,
                    'field' => $key,
                    'expected' => $value->getAmount(),
                    'actual' => $actual[$key]->getAmount(),
                ]);
            }
        }
        if ((string) $parsed->getId() !== (string) $invoice->number) {
            $errors[] = (string) __(':format: Rechnungsnummer weicht ab (:expected ≠ :actual).', [
                'format' => $label,
                'expected' => $invoice->number,
                'actual' => $parsed->getId(),
            ]);
        }

        return $errors;
    }
}
