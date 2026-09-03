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
            $this->expectExceptionMessage('Pflichtspalten fehlen');
            (new MarketplacePurchasesReader)->read((string) $file);
        } finally {
            @unlink((string) $file);
        }
    }
}
