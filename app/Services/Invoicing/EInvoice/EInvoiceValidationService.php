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

        $report['valid'] = $report['schema_errors'] === []
            && ($report['kosit_valid'] ?? true) !== false;

        return $report;
    }
}
