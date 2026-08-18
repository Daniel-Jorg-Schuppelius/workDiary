<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PdfGeneratorRegistryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Services\DocumentDesign\PdfGeneratorInventory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate für die PDF-Dokumentarten-Registrierung (Issue #83):
 * Jeder serverseitig erzeugte PDF-Typ muss über die Design-Pipeline
 * (`DocumentDesignRenderer::renderPdf`) laufen und in
 * {@see PdfGeneratorInventory} mit seinen Dokumentarten registriert sein.
 * Direkte PDF-Writer-Aufrufe (`createPdfString`) sind nur für die Pipeline
 * selbst und ausdrücklich begründete Ausnahmen zulässig.
 *
 * Ein neuer brandfähiger A4-PDF-Generator darf die Render-Pipeline damit
 * nicht unbemerkt umgehen; echte Spezialformate deklarieren ihre
 * Einschränkung über {@see RenderDocumentKind::isBrandable()} /
 * `capabilityNote()` statt still auszuscheren.
 */
class PdfGeneratorRegistryRuleTest extends TestCase {
    public function test_every_render_pdf_call_site_is_inventoried_with_matching_kinds(): void {
        $violations = [];
        $found = [];

        foreach ($this->appPhpFiles() as $relative => $source) {
            // InvoicePdfRenderer fährt die manuelle compose()-Pipeline
            // (createPdfString + registrierte RAW-Ausnahme) — zählt als Generator.
            $isGenerator = str_contains($source, '->renderPdf(')
                || (str_contains($source, 'createPdfString(') && isset(PdfGeneratorInventory::GENERATORS[$relative]));
            if (! $isGenerator) {
                continue;
            }
            $found[] = $relative;

            $declared = PdfGeneratorInventory::GENERATORS[$relative] ?? null;
            if ($declared === null) {
                $violations[] = $relative . ' ruft renderPdf() auf, ist aber nicht in PdfGeneratorInventory::GENERATORS registriert.';

                continue;
            }

            $used = $this->usedKindValues($source);
            if ($used === []) {
                $violations[] = $relative . ' übergibt keine erkennbare RenderDocumentKind-Konstante.';

                continue;
            }

            sort($used);
            $declaredSorted = $declared;
            sort($declaredSorted);
            if ($used !== $declaredSorted) {
                $violations[] = sprintf(
                    '%s: registrierte Arten [%s] passen nicht zu den verwendeten [%s].',
                    $relative,
                    implode(', ', $declaredSorted),
                    implode(', ', $used),
                );
            }
        }

        // Keine Karteileichen: registrierte Dateien müssen weiterhin rendern.
        foreach (array_keys(PdfGeneratorInventory::GENERATORS) as $relative) {
            if (! in_array($relative, $found, true)) {
                $violations[] = $relative . ' ist registriert, ruft aber kein renderPdf() mehr auf (Eintrag entfernen oder Aufruf wiederherstellen).';
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "PDF-Generator-Registrierung verletzt:\n - " . implode("\n - ", $violations)
            . "\nNeue Generatoren in App\\Services\\DocumentDesign\\PdfGeneratorInventory registrieren (Dokumentart ggf. in RenderDocumentKind ergänzen).");
    }

    public function test_raw_pdf_writer_calls_are_limited_to_declared_exceptions(): void {
        $violations = [];
        $found = [];

        foreach ($this->appPhpFiles() as $relative => $source) {
            if (! str_contains($source, 'createPdfString(')) {
                continue;
            }
            $found[] = $relative;

            if (! array_key_exists($relative, PdfGeneratorInventory::RAW_WRITER_CALLS)) {
                $violations[] = $relative . ' ruft createPdfString() direkt auf — über DocumentDesignRenderer::renderPdf() gehen oder als begründete Ausnahme in RAW_WRITER_CALLS eintragen.';
            }
        }

        foreach (array_keys(PdfGeneratorInventory::RAW_WRITER_CALLS) as $relative) {
            if (! in_array($relative, $found, true)) {
                $violations[] = $relative . ' steht in RAW_WRITER_CALLS, ruft aber kein createPdfString() mehr auf (Eintrag entfernen).';
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Direkte PDF-Writer-Aufrufe verletzen die Registrierung:\n - " . implode("\n - ", $violations));
    }

    public function test_inventory_kinds_exist_and_every_kind_is_covered(): void {
        $violations = [];

        foreach ($this->generators() as $file => $kinds) {
            foreach ($kinds as $kind) {
                if (RenderDocumentKind::tryFrom($kind) === null) {
                    $violations[] = $file . ' registriert unbekannte Dokumentart „' . $kind . '".';
                }
            }
        }
        foreach ($this->plannedKinds() as $kind) {
            if (RenderDocumentKind::tryFrom($kind) === null) {
                $violations[] = 'PLANNED_KINDS enthält unbekannte Dokumentart „' . $kind . '".';
            }
        }

        // Vollständigkeit: jede registrierte Art hat einen Generator oder ist
        // ausdrücklich als geplant deklariert — keine toten Registry-Einträge.
        $covered = PdfGeneratorInventory::coveredKindValues();
        foreach (RenderDocumentKind::cases() as $kind) {
            if (! in_array($kind->value, $covered, true)) {
                $violations[] = 'Dokumentart „' . $kind->value . '" hat weder Generator noch PLANNED_KINDS-Eintrag.';
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Inventar und Registrierung driften:\n - " . implode("\n - ", $violations));
    }

    /**
     * Const-Zugriff über Methodengrenze: generalisiert die Literal-Typen der
     * Inventar-Konstanten, damit die tryFrom-Drift-Prüfung nicht als
     * always-false wegfällt (PHPStan L8, treatPhpDocTypesAsCertain).
     *
     * @return array<string, array<int, string>>
     */
    private function generators(): array {
        return PdfGeneratorInventory::GENERATORS;
    }

    /** @return array<int, string> */
    private function plannedKinds(): array {
        return PdfGeneratorInventory::PLANNED_KINDS;
    }

    /** @return array<int, string> Im Quelltext verwendete Kind-Values (über Case-Namen aufgelöst). */
    private function usedKindValues(string $source): array {
        preg_match_all('/RenderDocumentKind::([A-Za-z]+)/', $source, $matches);
        $caseByName = [];
        foreach (RenderDocumentKind::cases() as $case) {
            $caseByName[$case->name] = $case->value;
        }

        $values = [];
        foreach ($matches[1] as $name) {
            if (isset($caseByName[$name])) {
                $values[] = $caseByName[$name];
            }
        }

        // Typabhängige Auflösung: forInvoiceType() deckt den Wertebereich
        // der Rechnungsbelege ab (invoice/credit_note/proforma_invoice).
        if (str_contains($source, 'RenderDocumentKind::forInvoiceType(')) {
            $values[] = RenderDocumentKind::Invoice->value;
            $values[] = RenderDocumentKind::CreditNote->value;
            $values[] = RenderDocumentKind::ProformaInvoice->value;
        }

        return array_values(array_unique($values));
    }

    /** @return iterable<string, string> repo-relativer Pfad → Quelltext */
    private function appPhpFiles(): iterable {
        $root = dirname(__DIR__, 3);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/app', RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            $relative = str_replace($root . '/', '', $file->getPathname());

            yield $relative => $source;
        }
    }
}
