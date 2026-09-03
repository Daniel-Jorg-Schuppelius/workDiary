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
use RuntimeException;
use Tests\TestCase;

class QualityHostingContractsReaderTest extends TestCase {
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
        $this->assertSame(150336, $premium->fee->getMinorAmount());
        $this->assertSame(18792, $premium->unitFee?->getMinorAmount());
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
    }

    public function test_missing_required_column_is_reported(): void {
        $path = sys_get_temp_dir() . '/qh-export-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($path, ['Kunde', 'Produktname'], [['Foo', 'Bar']]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Pflichtspalten fehlen');
            (new QualityHostingContractsReader)->read($path);
        } finally {
            @unlink($path);
        }
    }
}
