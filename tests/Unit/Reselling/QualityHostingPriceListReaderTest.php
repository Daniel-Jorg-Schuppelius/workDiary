<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingPriceListReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\QualityHostingPriceListReader;
use App\Support\XlsxExport;
use RuntimeException;
use Tests\TestCase;

class QualityHostingPriceListReaderTest extends TestCase {
    public function test_reads_prices_with_cover_sheet_validity(): void {
        $path = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        file_put_contents($path, XlsxExport::toStringMultiSheet([
            ['title' => 'Deckblatt', 'headers' => ['Preisliste für Reseller', ''], 'rows' => [['Reseller', '95229'], ['Gültigkeit ab', '01.09.2026'], ['Erstellt am', '03.09.2026']]],
            ['title' => 'Preisdaten', 'headers' => ['Produkttarif', 'Vertragslaufzeit in Monaten', 'Zahlungsintervall', 'Gültig ab', 'Preis pro Monat', 'Hersteller-UVP pro Monat', 'Preis pro Zahlungsintervall', 'Hersteller-UVP pro Zahlungsintervall', 'Hersteller-Key', 'Offer-Key'], 'rows' => [
                ['Microsoft 365 Business Premium', 1, 'monatlich', null, 18.8, 22.87, 18.8, 22.87, 'K:1', 'O-1M'],
                ['Microsoft 365 Business Premium', 12, 'jährlich', null, 15.66, 19.06, 187.92, 228.72, 'K:1', 'O-12M'],
                ['Exchange Online Plan 1', 12, 'jährlich', null, 2.86, 3.5, 34.32, 42, 'K:2', 'O-EXO'],
                ['Kaputt', 'x', 'wöchentlich', null, null, null, null, null, '', ''],
            ]],
        ]));

        try {
            $list = (new QualityHostingPriceListReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(3, $list->entries);
        $this->assertCount(1, $list->issues);
        $this->assertSame('2026-09-01', $list->validFrom?->toDateString());

        $yearly = $list->find('Microsoft 365 Business Premium', 12, BillingFrequency::Yearly);
        $this->assertNotNull($yearly);
        $this->assertSame(18792, $yearly->pricePerInterval->getMinorAmount());
        $this->assertSame(22872, $yearly->uvpPerInterval?->getMinorAmount());
        $this->assertSame(1566, $yearly->pricePerMonth->getMinorAmount());
        $this->assertSame('O-12M', $yearly->offerKey);

        $this->assertSame(1880, $list->find('Microsoft 365 Business Premium', 1, BillingFrequency::Monthly)?->pricePerInterval->getMinorAmount());
        $this->assertNotNull($list->find('Exchange Online (Plan 1)', 12, BillingFrequency::Yearly), 'Telekom-Schreibweise trifft denselben Produktschlüssel');
        $this->assertNull($list->find('Microsoft Teams Essentials', 12, BillingFrequency::Yearly));
    }

    public function test_missing_price_sheet_is_reported(): void {
        $path = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($path, ['Foo', 'Bar'], [['a', 'b']]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Produkttarif');
            (new QualityHostingPriceListReader)->read($path);
        } finally {
            @unlink($path);
        }
    }
}
