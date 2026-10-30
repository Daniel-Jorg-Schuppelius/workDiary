<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZoneRecognitionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\{TemplateFingerprint, ZoneExtractor};
use PDFToolkit\Entities\WordBox;
use PHPUnit\Framework\TestCase;

/** Fingerabdruck und Zonenauswertung (Phase 151) auf synthetischen Wortgittern. */
final class ZoneRecognitionTest extends TestCase {
    public function test_fingerprint_keeps_header_and_footer_words_without_numbers(): void {
        $boxes = [
            new WordBox(1, 0.1, 0.05, 0.2, 0.02, 'Rechenzentrum'),
            new WordBox(1, 0.3, 0.05, 0.1, 0.02, 'Ludwigs'),
            new WordBox(1, 0.1, 0.5, 0.2, 0.02, 'Mitte'),
            new WordBox(1, 0.1, 0.9, 0.2, 0.02, 'Sparkasse'),
            new WordBox(1, 0.4, 0.9, 0.2, 0.02, 'DE12345'),
            new WordBox(2, 0.1, 0.05, 0.2, 0.02, 'Seite2'),
        ];
        $fingerprint = TemplateFingerprint::fromBoxes($boxes);

        self::assertSame(['ludwigs', 'rechenzentrum', 'sparkasse'], $fingerprint['tokens']);
        self::assertEqualsWithDelta(1.0, TemplateFingerprint::similarity($fingerprint, $fingerprint), 1e-9);
        self::assertEqualsWithDelta(2 / 5, TemplateFingerprint::similarity($fingerprint, ['tokens' => ['ludwigs', 'rechenzentrum', 'postbank', 'berlin']]), 1e-9);
        self::assertSame(0.0, TemplateFingerprint::similarity($fingerprint, ['tokens' => []]));

        // Varianten (Phase 155): eine Vorlage merkt sich weitere Kopfformen, die Ähnlichkeit ist die beste über alle.
        $other = ['tokens' => ['postbank', 'berlin', 'muster']];
        $withVariant = TemplateFingerprint::withVariant($fingerprint, $other);
        $variant = $withVariant['variants'][0];
        sort($variant);
        self::assertSame(['berlin', 'muster', 'postbank'], $variant, 'Variante gemerkt');
        self::assertEqualsWithDelta(1.0, TemplateFingerprint::similarity($other, $withVariant), 1e-9);
        self::assertEqualsWithDelta(1.0, TemplateFingerprint::similarity($fingerprint, $withVariant), 1e-9);
        self::assertSame($withVariant, TemplateFingerprint::withVariant($withVariant, $other), 'eine bekannte Form wird nicht doppelt gemerkt');
        self::assertSame($fingerprint, TemplateFingerprint::withVariant($fingerprint, $fingerprint), 'die eigene Form ist keine Variante');
        $many = $withVariant;
        foreach (range(1, 6) as $i) {
            $many = TemplateFingerprint::withVariant($many, ['tokens' => ['form' . $i, 'x' . $i, 'y' . $i]]);
        }
        self::assertCount(TemplateFingerprint::MAX_VARIANTS, $many['variants'], 'die älteste Variante weicht');
    }

    public function test_zone_text_is_read_in_reading_order_and_parsed_by_field_type(): void {
        $boxes = [
            new WordBox(1, 0.70, 0.10, 0.12, 0.02, 'Rechnungs-Nr:'),
            new WordBox(1, 0.84, 0.10, 0.12, 0.02, 'AR20250831AD000815'),
            new WordBox(1, 0.70, 0.13, 0.08, 0.02, 'Starnberg,'),
            new WordBox(1, 0.80, 0.13, 0.08, 0.02, '31.08.2025'),
            new WordBox(1, 0.70, 0.80, 0.08, 0.02, '1.190,00'),
            new WordBox(1, 0.79, 0.80, 0.02, 0.02, '€'),
            new WordBox(2, 0.70, 0.10, 0.12, 0.02, 'Seite'),
        ];
        $extractor = new ZoneExtractor;

        self::assertSame('Rechnungs-Nr: AR20250831AD000815', $extractor->rawText(['page' => 1, 'x' => 0.65, 'y' => 0.09, 'w' => 0.35, 'h' => 0.04], $boxes));
        self::assertSame('AR20250831AD000815', $extractor->extract(['field' => 'invoice_number', 'page' => 1, 'x' => 0.83, 'y' => 0.09, 'w' => 0.17, 'h' => 0.04], $boxes));
        self::assertSame('2025-08-31', $extractor->extract(['field' => 'issue_date', 'page' => 1, 'x' => 0.65, 'y' => 0.12, 'w' => 0.35, 'h' => 0.04], $boxes));
        self::assertSame('1190.00', $extractor->extract(['field' => 'amount_gross', 'page' => 1, 'x' => 0.65, 'y' => 0.79, 'w' => 0.2, 'h' => 0.04], $boxes));
        self::assertNull($extractor->extract(['field' => 'amount_gross', 'page' => 2, 'x' => 0.65, 'y' => 0.09, 'w' => 0.35, 'h' => 0.04], $boxes), 'Text ohne Betrag ergibt keinen Wert');
        self::assertNull($extractor->extract(['field' => 'invoice_number', 'page' => 1, 'x' => 0.0, 'y' => 0.0, 'w' => 0.1, 'h' => 0.05], $boxes), 'leere Zone');
        self::assertSame('DE278737004', ZoneExtractor::parse('USt-IdNr. DE 278 737 004', 'vat_id'));
        self::assertSame('ATU12345678', ZoneExtractor::parse('UID-Nr.: ATU 12345678', 'vat_id'));
        self::assertNull(ZoneExtractor::parse('Steuernummer 12/345/67890', 'vat_id'), 'Steuernummer ist keine USt-IdNr.');
    }

    /** Anker-Zonen (Phase 155, E1/E2): Wert neben der Beschriftung, auf welcher Seite und in welcher Höhe sie steht. */
    public function test_an_anchored_zone_finds_its_value_next_to_the_label_on_any_page(): void {
        $extractor = new ZoneExtractor;
        // Beleg A: Summenblock auf Seite 1 bei y ≈ 0,78.
        $first = [
            new WordBox(1, 0.60, 0.78, 0.07, 0.02, 'Gesamt'),
            new WordBox(1, 0.68, 0.78, 0.04, 0.02, 'EUR'),
            new WordBox(1, 0.73, 0.78, 0.04, 0.02, 'inkl.'),
            new WordBox(1, 0.78, 0.78, 0.05, 0.02, 'MwSt.'),
            new WordBox(1, 0.90, 0.78, 0.06, 0.02, '366,39'),
        ];
        $drawn = ['field' => 'amount_gross', 'page' => 1, 'x' => 0.58, 'y' => 0.77, 'w' => 0.40, 'h' => 0.04];
        $split = $extractor->split($drawn, $first);
        self::assertSame('Gesamt EUR inkl. MwSt.', $split['anchor']);
        self::assertSame('366.39', $split['value']);
        self::assertNotNull($split['value_box']);
        self::assertEqualsWithDelta(0.60, $split['anchor_box']['x'], 1e-9);

        $zone = ['field' => 'amount_gross', 'scope' => 'any', 'anchor' => $split['anchor'], 'anchor_box' => $split['anchor_box'], 'page' => 1] + $split['value_box'];
        self::assertSame('366.39', $extractor->extract($zone, $first));

        // Beleg B: mehr Positionen, der Summenblock steht auf Seite 2 weiter oben — die feste Zone träfe nichts.
        $second = [
            new WordBox(1, 0.60, 0.78, 0.07, 0.02, 'Übertrag'),
            new WordBox(1, 0.90, 0.78, 0.06, 0.02, '812,00'),
            new WordBox(2, 0.60, 0.30, 0.07, 0.02, 'Gesamt'),
            new WordBox(2, 0.68, 0.30, 0.04, 0.02, 'EUR'),
            new WordBox(2, 0.73, 0.30, 0.04, 0.02, 'inkl.'),
            new WordBox(2, 0.78, 0.30, 0.05, 0.02, 'MwSt.'),
            new WordBox(2, 0.90, 0.30, 0.06, 0.02, '1.049,30'),
        ];
        self::assertSame('1049.30', $extractor->extract($zone, $second));
        self::assertSame('812.00', $extractor->extract(['scope' => 'page', 'anchor' => null] + $zone, $second), 'ohne Anker gilt die feste Lage');
        self::assertNull($extractor->extract(['scope' => 'first'] + $zone, $second), 'Bereich „erste Seite“ sieht Seite 2 nicht');
        self::assertSame('1049.30', $extractor->extract(['scope' => 'last'] + $zone, $second));
        self::assertNull($extractor->extract($zone, [new WordBox(1, 0.60, 0.78, 0.07, 0.02, 'Summe'), new WordBox(1, 0.90, 0.78, 0.06, 0.02, '5,00')]), 'ohne Anker kein Rückfall auf feste Koordinaten (E1)');

        // Das Kästchen bleibt, wie gezeichnet: Beschriftung und Wert darin, der Wert kommt aus dem Text des Kästchens.
        $box = ['field' => 'amount_gross', 'page' => 1, 'scope' => 'any', 'anchor' => $split['anchor'], 'anchor_box' => $split['anchor_box']] + $drawn;
        self::assertSame('366.39', $extractor->extract($box, $first));
        self::assertSame('1049.30', $extractor->extract($box, $second));
        self::assertSame('Gesamt EUR inkl. MwSt. 1.049,30', $extractor->rawText($box, $second));

        // Zeilenmuster liest aus dem Text im Kästchen; über zwei Zeilen trennt ein Zeilenumbruch.
        $pattern = ['pattern' => 'MwSt\.\s+([\d.,]+)'] + $box;
        self::assertSame('1049.30', $extractor->extract($pattern, $second));
        $twoRows = [
            new WordBox(1, 0.60, 0.74, 0.07, 0.02, 'Gesamt'),
            new WordBox(1, 0.68, 0.74, 0.04, 0.02, 'EUR'),
            new WordBox(1, 0.73, 0.74, 0.05, 0.02, 'ohne'),
            new WordBox(1, 0.79, 0.74, 0.05, 0.02, 'MwSt.'),
            new WordBox(1, 0.90, 0.74, 0.06, 0.02, '307,89'),
            ...$first,
        ];
        $wide = ['field' => 'amount_gross', 'page' => 1, 'x' => 0.58, 'y' => 0.73, 'w' => 0.40, 'h' => 0.08];
        self::assertSame("Gesamt EUR ohne MwSt. 307,89\nGesamt EUR inkl. MwSt. 366,39", $extractor->rawText($wide, $twoRows));
        self::assertSame('366.39', $extractor->extract($wide, $twoRows), 'ohne Muster der letzte Betrag im Kästchen');
        self::assertSame('307.89', $extractor->extract(['pattern' => 'ohne MwSt\.\s+([\d.,]+)'] + $wide, $twoRows));
        self::assertNull($extractor->extract(['pattern' => 'ohne MwSt\..*inkl'] + $wide, $twoRows), '„.“ überspringt keinen Zeilenumbruch');
        self::assertFalse(ZoneExtractor::isValidPattern('(unclosed'));
        self::assertFalse(ZoneExtractor::isValidPattern(str_repeat('a', 201)));
        self::assertTrue(ZoneExtractor::isValidPattern('Brutto\s+(\d+,\d{2})'));
        self::assertCount(1, $extractor->hits($zone, $second));
    }

    public function test_values_are_checked_for_plausibility_by_field_type(): void {
        self::assertNull(ZoneExtractor::parse('19,00 % Umsatzsteuer', 'money'), 'Ein Betrag vor einem Prozentzeichen ist ein Satz');
        self::assertSame('58.50', ZoneExtractor::parse('MwSt. 19,00 % 58,50', 'money'), 'Der Satz wird übersprungen, der letzte Betrag zählt');
        self::assertSame('63.99', ZoneExtractor::parse('Rechnungsbetrag Brutto 63,99', 'money'));
        self::assertNull(ZoneExtractor::parse('13.01.2025', 'id'), 'Ein Datum ist keine Rechnungsnummer');
        self::assertNull(ZoneExtractor::parse('Patrizia Penner', 'id'), 'Eine Kennung braucht eine Ziffer');
        self::assertSame('DR8407973', ZoneExtractor::parse('Rechnung Nr. DR8407973', 'id'));
        self::assertSame('2025-01-13', ZoneExtractor::parse('Belegdatum 13.01.2025', 'date'));

        $extractor = new ZoneExtractor;
        $boxes = [
            new WordBox(1, 0.10, 0.20, 0.10, 0.02, 'Belegdatum'),
            new WordBox(1, 0.30, 0.20, 0.10, 0.02, '13.01.2025'),
            new WordBox(1, 0.10, 0.23, 0.10, 0.02, 'Bearbeiter'),
            new WordBox(1, 0.30, 0.23, 0.10, 0.02, 'Petra'),
        ];
        $split = $extractor->split(['field' => 'issue_date', 'page' => 1, 'x' => 0.05, 'y' => 0.19, 'w' => 0.5, 'h' => 0.03], $boxes);
        self::assertSame('Belegdatum', $split['anchor']);
        self::assertSame('2025-01-13', $split['value']);
        $plain = $extractor->split(['field' => 'issue_date', 'page' => 1, 'x' => 0.28, 'y' => 0.19, 'w' => 0.2, 'h' => 0.03], $boxes);
        self::assertNull($plain['anchor'], 'ohne Beschriftungswörter bleibt die Zone fest');
        self::assertSame('2025-01-13', $plain['value']);

        // Beschriftungswörter links eines gelernten Wertes werden zum Anker, Zahlen und ferne Wörter nicht.
        $row = [
            new WordBox(1, 0.05, 0.50, 0.08, 0.02, 'Zahlbar'),
            new WordBox(1, 0.14, 0.50, 0.02, 0.02, '8'),
            new WordBox(1, 0.17, 0.50, 0.05, 0.02, 'Tage'),
            new WordBox(1, 0.60, 0.50, 0.07, 0.02, 'Gesamt'),
            new WordBox(1, 0.68, 0.50, 0.04, 0.02, 'EUR'),
            new WordBox(1, 0.90, 0.50, 0.06, 0.02, '366,39'),
        ];
        $anchor = $extractor->anchorWordsLeftOf($row[5], $row);
        self::assertSame(['Gesamt', 'EUR'], array_map(static fn (WordBox $w): string => $w->text, $anchor));
    }

    /** Konsolidierungs-Audit 2026-10-21, ko-02/tk-06: derselbe Betragsleser wie die Heuristik, USt-IdNr. nur im gültigen Format. */
    public function test_amounts_in_every_notation_and_only_valid_vat_ids(): void {
        self::assertSame('1234.56', ZoneExtractor::parse('Total 1,234.56 USD', 'money'));
        self::assertSame('1234.56', ZoneExtractor::parse('Total USD 1,234.56', 'money'));
        self::assertSame('1234.56', ZoneExtractor::parse('Gesamt 1.234,56 EUR', 'money'));
        self::assertSame('1234.56', ZoneExtractor::parse('Summe 1 234,56', 'money'));
        self::assertSame('234.56', ZoneExtractor::parse('MwSt 19,00 % 234,56', 'money'), 'Ein Satz vor dem Prozentzeichen ist kein Betrag.');
        self::assertNull(ZoneExtractor::parse('Seite 2 von 3', 'money'));
        self::assertNull(ZoneExtractor::parse('USt-IdNr. DE12345', 'vat_id'), 'DE mit falscher Länge ist keine USt-IdNr.');
        self::assertSame('ZA4123456789', ZoneExtractor::parse('VAT Reg. No. ZA 4123456789', 'vat_id'), 'Länder ohne Toolkit-Format bleiben beim Rückfall.');
        self::assertNull(ZoneExtractor::parse('MWST Nummer folgt', 'vat_id'));
    }
}
