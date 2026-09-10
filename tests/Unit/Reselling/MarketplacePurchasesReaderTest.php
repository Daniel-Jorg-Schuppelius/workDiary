<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplacePurchasesReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\MarketplacePurchasesReader;
use RuntimeException;
use Tests\TestCase;

class MarketplacePurchasesReaderTest extends TestCase {
    private const FIXTURE = __DIR__ . '/../../Fixtures/Reselling/marketplace-purchases.csv';

    public function test_reads_export_with_bom_nbsp_fees_and_two_digit_years(): void {
        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);

        $this->assertCount(5, $import->entitlements);
        $this->assertCount(1, $import->issues);
        $this->assertStringContainsString('Wöchentlich', $import->issues[0]);
        $this->assertStringContainsString('Zeile 7', $import->issues[0]);

        $first = $import->entitlements[0];
        $this->assertSame('Muster Bau GmbH', $first->company->name);
        $this->assertSame('100001', $first->company->key);
        $this->assertSame('0301234567', $first->company->phone, 'Kopfzeile mit Leerzeichen-Vorlauf muss gefunden werden');
        $this->assertSame('max@musterbau.test', $first->company->email);
        $this->assertSame('ent-0001', $first->entitlementId);
        $this->assertSame('5000001', $first->orderId);
        $this->assertSame('Microsoft 365 Business Premium', $first->edition);
        $this->assertSame(195807, $first->fee->getMinorAmount());
        $this->assertSame('EUR', $first->fee->getCurrency()->value);
        $this->assertSame(BillingFrequency::Yearly, $first->frequency);
        $this->assertSame('2024-08-02', $first->startsOn->toDateString());
        $this->assertSame('2026-08-02', $first->endsOn->toDateString());
        $this->assertSame('CANCELLED', $first->status);
        $this->assertSame(2, $first->sourceLine);

        $this->assertCount(3, $import->companies());
        $this->assertCount(2, $import->byCompany()['100002']);
    }

    public function test_missing_required_column_is_reported(): void {
        $file = tempnam(sys_get_temp_dir(), 'purchases');
        file_put_contents($file, "Owner Company Name,Edition Name\nFoo,Bar\n");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(__('resale_import.file.missing_columns', ['columns' => 'company entitlement uuid, active order id, active order frequency, active order total fee, active order contract end date, creation date']));
            (new MarketplacePurchasesReader)->read((string) $file);
        } finally {
            @unlink((string) $file);
        }
    }

    /**
     * @param  list<string>  $rows
     */
    private function export(array $rows, string $encoding = 'UTF-8'): string {
        $header = 'Owner Company Name,Owner Company ID, Owner Company Phone,Company Entitlement UUID,Edition Name,Active Order ID,Active Order Frequency,Active Order Total Fee,Active Order Contract End Date,Currency,Creation Date,Status';
        $content = $header . "\n" . implode("\n", $rows) . "\n";
        $file = sys_get_temp_dir() . '/purchases-' . uniqid() . '.csv';
        file_put_contents($file, $encoding === 'UTF-8' ? "\xEF\xBB\xBF" . $content : (string) iconv('UTF-8', $encoding, $content));

        return $file;
    }

    public function test_ragged_rows_negative_fees_and_us_dates_are_handled_per_line(): void {
        $file = $this->export([
            'Muster Bau GmbH,100001,030,ent-1,Microsoft 365 Business Premium,5000001,Jährlich,"1.958,07 €",02.08.26,EUR,02.08.24',
            'Muster Bau GmbH,100001,030,ent-2,Microsoft 365 Business Premium,5000002,Jährlich,"-244,76 €",07.10.26,EUR,07.10.24,CANCELLED',
            'Beispiel Logistik,100002,033,ent-3,Exchange Online (Plan 1),5000003,Jährlich,"41,55 €",8/15/25,EUR,8/15/23,ACTIVE',
            'Beispiel Logistik,100002,033,ent-4,Exchange Online (Plan 1),5000004,Jährlich,"41,55 €",1/2/2027,EUR,1/2/2025,ACTIVE',
            'Kaputt AG,100003,033,ent-5,Exchange Online (Plan 1),5000005,Jährlich,"41,55 €",2027,EUR,1/2/2025,ACTIVE',
            'Zuviel AG,100004,033,ent-6,Exchange Online (Plan 1),5000006,Jährlich,"41,55 €",02.08.26,EUR,02.08.24,ACTIVE,extra,zuviel',
        ]);
        try {
            $import = (new MarketplacePurchasesReader)->read($file);
        } finally {
            @unlink($file);
        }

        $this->assertCount(4, $import->entitlements);
        [$short, $negative, $us, $de] = $import->entitlements;
        $this->assertSame('ent-1', $short->entitlementId, 'fehlende Endspalten gelten als leer');
        $this->assertSame('', $short->status);
        $this->assertSame(-24476, $negative->fee->getMinorAmount(), 'negative Gebühr bleibt negativ');
        $this->assertSame('2023-08-15', $us->startsOn->toDateString(), '„8/15/23" ist deutsch unmöglich → US-Reihenfolge');
        $this->assertSame('2025-08-15', $us->endsOn->toDateString());
        $this->assertSame('2025-02-01', $de->startsOn->toDateString(), '„1/2/2025" ist deutsch: 1. Februar');
        $this->assertSame('2027-02-01', $de->endsOn->toDateString());

        $this->assertCount(2, $import->issues);
        $this->assertSame(__('resale_import.row.dates_unreadable', ['line' => 6, 'company' => 'Kaputt AG', 'start' => '1/2/2025', 'end' => '2027']), $import->issues[0]);
        $this->assertSame(__('resale_import.row.too_many_fields', ['line' => 7, 'expected' => 12, 'found' => 14]), $import->issues[1]);
    }

    public function test_windows_1252_export_is_converted(): void {
        $file = $this->export([
            'Müller & Söhne GmbH,100001,030,ent-1,Microsoft 365 Business Premium,5000001,Jährlich,"1.958,07",02.08.26,EUR,02.08.24,ACTIVE',
        ], 'Windows-1252');
        try {
            $import = (new MarketplacePurchasesReader)->read($file);
        } finally {
            @unlink($file);
        }

        $this->assertSame([], $import->issues);
        $this->assertCount(1, $import->entitlements);
        $this->assertSame('Müller & Söhne GmbH', $import->entitlements[0]->company->name);
        $this->assertSame(195807, $import->entitlements[0]->fee->getMinorAmount());
    }
}
