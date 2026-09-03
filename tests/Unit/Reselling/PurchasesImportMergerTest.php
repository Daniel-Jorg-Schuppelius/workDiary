<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchasesImportMergerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, MarketplaceEntitlement, PurchasesImport, PurchasesImportMerger};
use Tests\TestCase;

class PurchasesImportMergerTest extends TestCase {
    use BuildsEntitlements;

    public function test_companies_are_unified_by_name_with_quality_hosting_key_and_partner_number(): void {
        $telekom = $this->entitlement('Microsoft 365 Business Standard', '138.94', '2024-07-16', '2026-07-16', BillingFrequency::Yearly, 'Kindheit');
        $qh = $this->entitlement('Microsoft 365 Business Standard', '120.36', '2025-07-16', null, BillingFrequency::Yearly, 'Kindheit ', MarketplaceEntitlement::SOURCE_QUALITYHOSTING, 1)
            ->withCompany(new MarketplaceCompany('CNL00025', 'Kindheit ', null, null, '10099'));

        $merged = (new PurchasesImportMerger)->merge(
            new PurchasesImport([$telekom], ['Telekom-Befund']),
            new PurchasesImport([$qh], []),
        );

        $this->assertCount(2, $merged->entitlements);
        $this->assertCount(1, $merged->companies(), 'gleicher normalisierter Name → eine Firma');
        $company = array_values($merged->companies())[0];
        $this->assertSame('CNL00025', $company->key, 'Schlüssel des aktuellen Systems gewinnt');
        $this->assertSame('10099', $company->partnerCustomerNumber);
        $this->assertSame(['Telekom-Befund'], $merged->issues);
        $this->assertSame(['telekom' => 1, 'qualityhosting' => 1], $merged->countBySource());
        $this->assertCount(1, $merged->links, 'Ablösung wird beim Zusammenführen erkannt');
        $this->assertSame('2025-07-16', $merged->entitlements[0]->endsOn?->toDateString());
    }
}
