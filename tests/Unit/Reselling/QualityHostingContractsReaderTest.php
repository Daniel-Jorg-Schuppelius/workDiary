<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingContractsReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\{MarketplaceEntitlement, QualityHostingContractsReader};
use App\Support\XlsxExport;
use DateTimeImmutable;
use RuntimeException;
use Tests\TestCase;

class QualityHostingContractsReaderTest extends TestCase {
    use BuildsXlsxFixtures;

    public const HEADERS = ['Kundennummer', 'Kunde', 'Produktname', 'Gekaufte Lizenzen', 'Preis pro Lizenz (Vertragslaufzeit)', 'Gesamtpreis (Vertragslaufzeit)', 'Preis pro Lizenz (pro Monat)', 'Gesamtpreis (pro Monat)', 'Vertragslaufzeit', 'Abrechnungsintervall', 'Vertragsnummer', 'Vertragsstart', 'Vertragsverlängerung', 'Vertragsstatus', 'Tarifnummer', 'Partner-Kundennummer'];

    /**
     * Anonymisierter Export im Format des Partnerportals; Vertragsstart als
     * Excel-Seriennummer (45871 = 02.08.2025), einmal als Text.
     *
     * @return list<list<int|float|string|null>>
     */
    public static function rows(): array {
        return [
            ['CNL00007', 'Muster Bau GmbH', 'Microsoft 365 Business Premium', 8, 187.92, 1503.36, 15.66, 125.28, 12, 'Jährlich', 'CNLCON00167', 45871, 46601, 'Aktiv, verlängert sich am 02.08.2027', 95, null],
            ['CNL00009', ' Beispiel Logistik', 'Exchange Online Plan 1', 7, 34.42, 240.94, 2.8683333, 20.078333, 12, 'Jährlich', 'CNLCON00131', '02.04.2026', 46479, 'Aktiv, verlängert sich am 02.04.2027', 270929, '10031'],
            ['CNL00016', 'Alt AG', 'Microsoft Teams Essentials', 1, 36.39, 36.39, 3.0325, 3.0325, 12, 'Jährlich', 'CNLCON00170', 45982, 46347, 'Gekündigt zum 21.11.2026', 537, null],
            ['CNL00017', 'Fehler UG', 'Microsoft 365 Business Basic', 1, 51.22, 51.22, 4.27, 4.27, 12, 'Wöchentlich', 'CNLCON00999', 45982, 46347, 'Aktiv, verlängert sich am 21.11.2026', 270883, null],
            ['Anzahl: 4', null, null, 'Summe: 17', null, 'Summe: 1831,91', null, null, null, null, null, null, null, null, null, null],
        ];
    }

    public static function writeFixture(): string {
        $path = sys_get_temp_dir() . '/qh-export-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($path, self::HEADERS, self::rows());

        return $path;
    }

    public function test_reads_contracts_with_serial_dates_quantities_and_partner_numbers(): void {
        $path = self::writeFixture();
        try {
            $import = (new QualityHostingContractsReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(3, $import->entitlements);
        $this->assertCount(1, $import->issues);
        $this->assertStringContainsString('Wöchentlich', $import->issues[0]);

        $premium = $import->entitlements[0];
        $this->assertSame(MarketplaceEntitlement::SOURCE_QUALITYHOSTING, $premium->source);
        $this->assertSame('CNL00007', $premium->company->key);
        $this->assertSame('Muster Bau GmbH', $premium->company->name);
        $this->assertNull($premium->company->partnerCustomerNumber);
        $this->assertSame('CNLCON00167', $premium->entitlementId);
        $this->assertSame(8, $premium->quantity);
        $this->assertSame(150336, $premium->fee->getMinorAmount(), 'Gesamtpreis Scale 2');
        $this->assertSame('187.9200', $premium->unitFee?->getAmount(), 'Stückpreis Scale 4 (B19)');
        $this->assertSame(BillingFrequency::Yearly, $premium->frequency);
        $this->assertSame('2025-08-02', $premium->startsOn->toDateString());
        $this->assertNull($premium->endsOn, 'aktiv mit Verlängerung = offenes Ende');
        $this->assertSame(2, $premium->sourceLine);

        $exchange = $import->entitlements[1];
        $this->assertSame('Beispiel Logistik', $exchange->company->name, 'führendes Leerzeichen entfernt');
        $this->assertSame('10031', $exchange->company->partnerCustomerNumber);
        $this->assertSame('2026-04-02', $exchange->startsOn->toDateString(), 'Datum als Text');
        $this->assertSame(7, $exchange->quantity);

        $terminated = $import->entitlements[2];
        $this->assertSame('2026-11-21', $terminated->endsOn?->toDateString(), 'Kündigungsdatum aus dem Status');
        $this->assertSame('36.3900', $terminated->unitFee?->getAmount());
        $this->assertSame(12, $terminated->termMonths);
    }

    public function test_date_cells_four_decimal_prices_and_quantity_issues(): void {
        $path = self::xlsxFixture([['Verträge', [
            self::HEADERS,
            ['CNL00016', 'Alt AG', 'Microsoft Teams Essentials', 1, 3.0325, 3.0325, 3.0325, 3.0325, 1, 'Monatlich', 'CNLCON00170', new DateTimeImmutable('2025-11-21'), null, 'Aktiv, verlängert sich am 21.12.2025', 537, null],
            ['CNL00018', 'Menge GmbH', 'Exchange Online Plan 1', '4 Stück', 34.42, 137.68, 2.87, 11.47, 12, 'Jährlich', 'CNLCON00171', '1/2/2026', null, 'Aktiv', 270929, null],
            ['CNL00019', 'Datum GmbH', 'Exchange Online Plan 1', 2, 34.42, 68.84, 2.87, 5.74, 12, 'Jährlich', 'CNLCON00172', '31.02.2026', null, 'Aktiv', 270929, null],
            ['CNL00020', 'Preis GmbH', 'Exchange Online Plan 1', 2, '1.200', '2.400', null, null, 12, 'Jährlich', 'CNLCON00173', 46054, null, 'Aktiv', 270929, null],
        ]]], 'qh-export');
        try {
            $import = (new QualityHostingContractsReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $import->entitlements);
        $this->assertCount(2, $import->issues);
        $this->assertStringContainsString('Zeile 3', $import->issues[0]);
        $this->assertStringContainsString('"4 Stück"', $import->issues[0]);
        $this->assertStringContainsString('Zeile 4', $import->issues[1]);
        $this->assertStringContainsString('"31.02.2026"', $import->issues[1]);

        $teams = $import->entitlements[0];
        $this->assertSame('2025-11-21', $teams->startsOn->toDateString(), 'echte Excel-Datumszelle');
        $this->assertSame('3.0325', $teams->unitFee?->getAmount(), '3,0325 bleibt vierstellig statt 3,03');
        $this->assertSame('3.03', $teams->fee->getAmount(), 'Gesamtpreis auf Cent');
        $this->assertSame(1, $teams->termMonths);

        $text = $import->entitlements[1];
        $this->assertSame('2026-02-01', $text->startsOn->toDateString(), 'Seriennummer 46054');
        $this->assertSame('1200.0000', $text->unitFee?->getAmount(), 'Textzelle „1.200" deutsch');
        $this->assertSame('2400.00', $text->fee->getAmount());
    }

    public function test_missing_required_column_is_reported(): void {
        $path = sys_get_temp_dir() . '/qh-export-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($path, ['Kunde', 'Produktname'], [['Foo', 'Bar']]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(__('resale_import.file.missing_columns', ['columns' => 'kundennummer, gekaufte lizenzen, gesamtpreis (vertragslaufzeit), abrechnungsintervall, vertragsnummer, vertragsstart, vertragsstatus']));
            (new QualityHostingContractsReader)->read($path);
        } finally {
            @unlink($path);
        }
    }
}
