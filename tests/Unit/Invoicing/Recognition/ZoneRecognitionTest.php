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
    }

    public function test_zone_text_is_read_in_reading_order_and_parsed_by_field_type(): void {
        $boxes = [
            new WordBox(1, 0.70, 0.10, 0.12, 0.02, 'Rechnungs-Nr:'),
            new WordBox(1, 0.84, 0.10, 0.12, 0.02, 'AR20250831AD012409'),
            new WordBox(1, 0.70, 0.13, 0.08, 0.02, 'Starnberg,'),
            new WordBox(1, 0.80, 0.13, 0.08, 0.02, '31.08.2025'),
            new WordBox(1, 0.70, 0.80, 0.08, 0.02, '1.190,00'),
            new WordBox(1, 0.79, 0.80, 0.02, 0.02, '€'),
            new WordBox(2, 0.70, 0.10, 0.12, 0.02, 'Seite'),
        ];
        $extractor = new ZoneExtractor;

        self::assertSame('Rechnungs-Nr: AR20250831AD012409', $extractor->rawText(['page' => 1, 'x' => 0.65, 'y' => 0.09, 'w' => 0.35, 'h' => 0.04], $boxes));
        self::assertSame('AR20250831AD012409', $extractor->extract(['field' => 'invoice_number', 'page' => 1, 'x' => 0.83, 'y' => 0.09, 'w' => 0.17, 'h' => 0.04], $boxes));
        self::assertSame('2025-08-31', $extractor->extract(['field' => 'issue_date', 'page' => 1, 'x' => 0.65, 'y' => 0.12, 'w' => 0.35, 'h' => 0.04], $boxes));
        self::assertSame('1190.00', $extractor->extract(['field' => 'amount_gross', 'page' => 1, 'x' => 0.65, 'y' => 0.79, 'w' => 0.2, 'h' => 0.04], $boxes));
        self::assertNull($extractor->extract(['field' => 'amount_gross', 'page' => 2, 'x' => 0.65, 'y' => 0.09, 'w' => 0.35, 'h' => 0.04], $boxes), 'Text ohne Betrag ergibt keinen Wert');
        self::assertNull($extractor->extract(['field' => 'invoice_number', 'page' => 1, 'x' => 0.0, 'y' => 0.0, 'w' => 0.1, 'h' => 0.05], $boxes), 'leere Zone');
        self::assertSame('DE278737004', ZoneExtractor::parse('USt-IdNr. DE 278 737 004', 'vat_id'));
        self::assertSame('ATU12345678', ZoneExtractor::parse('UID-Nr.: ATU 12345678', 'vat_id'));
        self::assertNull(ZoneExtractor::parse('Steuernummer 12/345/67890', 'vat_id'), 'Steuernummer ist keine USt-IdNr.');
    }
}
