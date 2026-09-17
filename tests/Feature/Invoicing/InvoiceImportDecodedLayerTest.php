<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceImportDecodedLayerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Services\Invoicing\InvoicePdfImportService;
use Tests\TestCase;

/**
 * Entzifferter Textlayer im Belegimport (MVP-808, pdf-toolkit ^0.17): Die
 * Abnahme des Dekodats liegt beim Aufrufer. Übernommen wird es nur, wenn es
 * mehr erkennt als der bisherige Text und die Summen aufgehen.
 */
final class InvoiceImportDecodedLayerTest extends TestCase {
    private const DECODED = "Rechnungsnummer RE-2026-0815\nRechnungsdatum 03.09.2026\nNettobetrag 1.000,00 EUR\nUmsatzsteuer 19 % 190,00 EUR\nRechnungsbetrag 1.190,00 EUR";

    /** Stabile Buchstabenvertauschung, wie sie ein Nachdruck ohne ToUnicode-Tabelle liefert. */
    private const GARBLED = "Sqfgmtmdrmtoopq SQ-2026-0815\nSqfgmtmdrchwto 03.09.2026\nMqwwnxqwsch 1.000,00 QTS";

    public function test_decoded_layer_wins_over_garbled_text(): void {
        $this->assertTrue($this->service()->prefersDecodedLayer(self::GARBLED, self::DECODED));
    }

    public function test_decoded_layer_with_inconsistent_totals_is_rejected(): void {
        // Eine falsch gelernte Ziffer: 1.190,00 wird zu 1.790,00.
        $wrong = str_replace('1.190,00', '1.790,00', self::DECODED);

        $this->assertFalse($this->service()->prefersDecodedLayer(self::GARBLED, $wrong));
    }

    public function test_readable_text_is_not_replaced_by_an_equal_decode(): void {
        $this->assertFalse($this->service()->prefersDecodedLayer(self::DECODED, self::DECODED));
    }

    private function service(): InvoicePdfImportService {
        return app(InvoicePdfImportService::class);
    }
}
