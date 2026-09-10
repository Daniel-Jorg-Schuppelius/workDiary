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
use DateTimeImmutable;
use RuntimeException;
use Tests\TestCase;

class QualityHostingPriceListReaderTest extends TestCase {
    use BuildsXlsxFixtures;

    private const HEADERS = ['Produkttarif', 'Vertragslaufzeit in Monaten', 'Zahlungsintervall', 'Gültig ab', 'Preis pro Monat', 'Hersteller-UVP pro Monat', 'Preis pro Zahlungsintervall', 'Hersteller-UVP pro Zahlungsintervall', 'Hersteller-Key', 'Offer-Key'];

    public function test_reads_prices_with_cover_sheet_validity(): void {
        $path = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        file_put_contents($path, XlsxExport::toStringMultiSheet([
            ['title' => 'Deckblatt', 'headers' => ['Preisliste für Reseller', ''], 'rows' => [['Reseller', '95229'], ['Gültigkeit ab', '01.09.2026'], ['Erstellt am', '03.09.2026']]],
            ['title' => 'Preisdaten', 'headers' => self::HEADERS, 'rows' => [
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
        $this->assertStringContainsString('Zeile 5', $list->issues[0]);
        $this->assertSame('2026-09-01', $list->validFrom?->toDateString());

        $yearly = $list->find('Microsoft 365 Business Premium', 12, BillingFrequency::Yearly);
        $this->assertNotNull($yearly);
        $this->assertSame('187.9200', $yearly->pricePerInterval->getAmount(), 'Stückpreise mit Scale 4 (B19)');
        $this->assertSame(22872, $yearly->uvpPerInterval?->withScale(2)->getMinorAmount());
        $this->assertSame(1566, $yearly->pricePerMonth->withScale(2)->getMinorAmount());
        $this->assertSame('O-12M', $yearly->offerKey);
        $this->assertSame('2026-09-01', $yearly->validFrom?->toDateString(), 'ohne Spaltenwert gilt das Deckblatt je Zeile');

        $this->assertSame(1880, $list->find('Microsoft 365 Business Premium', 1, BillingFrequency::Monthly)?->pricePerInterval->withScale(2)->getMinorAmount());
        $this->assertNotNull($list->find('Exchange Online (Plan 1)', 12, BillingFrequency::Yearly), 'Telekom-Schreibweise trifft denselben Produktschlüssel');
        $this->assertNull($list->find('Microsoft Teams Essentials', 12, BillingFrequency::Yearly));
    }

    public function test_cover_sheet_date_cell_and_row_validity_win_over_cover(): void {
        $path = self::xlsxFixture([
            ['Deckblatt', [['Erstellt am', new DateTimeImmutable('2026-09-03')], ['Reseller', '95229'], ['Gültigkeit ab', new DateTimeImmutable('2026-09-01')]]],
            ['Preisdaten', [
                self::HEADERS,
                ['Microsoft 365 Business Premium', 1, 'monatlich', null, 18.8, 22.87, 18.8, 22.87, 'K:1', 'O-1M'],
                ['Microsoft 365 Business Premium', 12, 'jährlich', new DateTimeImmutable('2026-08-15'), 15.66, 19.06, 187.92, 228.72, 'K:1', 'O-12M'],
                ['Exchange Online Plan 1', 12, 'jährlich', 'kaputt', 2.8683, 3.5, 34.42, 42, 'K:2', 'O-EXO'],
            ]],
        ], 'qh-prices');
        try {
            $list = (new QualityHostingPriceListReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(3, $list->entries, 'Datumszelle in der ersten Deckblatt-Zeile kippt den Import nicht');
        $this->assertSame('2026-08-15', $list->validFrom?->toDateString(), 'frühester Zeilenwert vor dem Deckblatt');
        [$monthly, $yearly, $exchange] = $list->entries;
        $this->assertSame('2026-09-01', $monthly->validFrom?->toDateString(), 'Deckblatt als Datumszelle');
        $this->assertSame('2026-08-15', $yearly->validFrom?->toDateString(), 'Zeilenwert „Gültig ab"');
        $this->assertSame('2026-09-01', $exchange->validFrom?->toDateString(), 'unlesbarer Zeilenwert → Deckblatt');
        $this->assertSame('2.8683', $exchange->pricePerMonth->getAmount(), 'vier Nachkommastellen bleiben');
        $this->assertCount(1, $list->issues);
        $this->assertSame(__('resale_import.pricelist.valid_from_unreadable', ['line' => 4, 'product' => 'Exchange Online Plan 1', 'value' => 'kaputt']), $list->issues[0]);
    }

    public function test_without_any_validity_the_list_has_no_date_and_reports_it(): void {
        $path = self::xlsxFixture([['Preisdaten', [
            self::HEADERS,
            ['Microsoft 365 Business Premium', 1, 'monatlich', null, 18.8, 22.87, 18.8, 22.87, 'K:1', 'O-1M'],
        ]]], 'qh-prices');
        try {
            $list = (new QualityHostingPriceListReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $list->entries);
        $this->assertNull($list->validFrom, 'der Importer setzt dann das Importdatum');
        $this->assertNull($list->entries[0]->validFrom);
        $this->assertSame([__('resale_import.pricelist.no_valid_from')], $list->issues);
    }

    public function test_missing_price_sheet_is_reported(): void {
        $path = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($path, ['Foo', 'Bar'], [['a', 'b']]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(__('resale_import.pricelist.no_sheet'));
            (new QualityHostingPriceListReader)->read($path);
        } finally {
            @unlink($path);
        }
    }
}
