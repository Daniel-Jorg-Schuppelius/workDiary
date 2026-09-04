<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSuccessionLinkerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\{ContractSuccessionLinker, MarketplaceEntitlement};
use Tests\TestCase;

class ContractSuccessionLinkerTest extends TestCase {
    use BuildsEntitlements;

    private const QH = MarketplaceEntitlement::SOURCE_QUALITYHOSTING;

    public function test_telekom_term_is_capped_at_the_successor_start(): void {
        $telekom = $this->entitlement('Microsoft 365 Business Premium', '1958.07', '2024-08-02', '2026-08-02');
        $unit = $this->entitlement('Microsoft 365 Business Premium', '244.76', '2024-08-02', '2026-08-02');
        $qh = $this->entitlement('Microsoft 365 Business Premium', '1503.36', '2025-08-02', null, BillingFrequency::Yearly, 'Muster Bau GmbH', self::QH, 8);

        $result = (new ContractSuccessionLinker)->link([$telekom, $unit, $qh]);

        $this->assertCount(1, $result['links']);
        $capped = $result['entitlements'][0];
        $this->assertSame('2025-08-02', $capped->endsOn?->toDateString());
        $this->assertStringContainsString('abgelöst durch Quality Hosting ' . $qh->entitlementId . ' ab 02.08.2025', $capped->successionNote);
        $this->assertSame('', $result['entitlements'][1]->successionNote, 'Menge 1 passt nicht zu Menge 8');

    }

    public function test_same_day_migration_leaves_no_telekom_period(): void {
        $telekom = $this->entitlement('Microsoft 365 Business Standard', '128.38', '2026-02-13', '2027-02-13');
        $qh = $this->entitlement('Microsoft 365 Business Standard', '106.35', '2026-02-13', null, BillingFrequency::Yearly, 'Muster Bau GmbH', self::QH, 1);

        $result = (new ContractSuccessionLinker)->link([$telekom, $qh]);

        $this->assertCount(1, $result['links']);
    }

    public function test_co_term_anniversary_links_despite_different_creation_day(): void {
        // Telekom-Position vom 28.03., co-termed auf den 02.04.; QH startet am 02.04.2026.
        $telekom = $this->entitlement('Exchange Online (Plan 1)', '290.88', '2023-03-28', '2027-04-02');
        $unit = $this->entitlement('Exchange Online (Plan 1)', '41.55', '2023-03-28', '2027-04-02');
        $qh = $this->entitlement('Exchange Online Plan 1', '240.94', '2026-04-02', null, BillingFrequency::Yearly, 'Muster Bau GmbH', self::QH, 7);

        $result = (new ContractSuccessionLinker)->link([$telekom, $unit, $qh]);

        $this->assertCount(1, $result['links']);
        $this->assertSame($telekom->entitlementId, $result['links'][0]->predecessor->entitlementId);
        $this->assertSame('2026-04-02', $result['entitlements'][0]->endsOn?->toDateString());
    }

    public function test_no_link_across_companies_or_far_anniversaries(): void {
        $telekom = $this->entitlement('Microsoft Teams Essentials', '43.98', '2023-11-21', '2026-11-21');
        $otherCompany = $this->entitlement('Microsoft Teams Essentials', '36.39', '2025-11-21', null, BillingFrequency::Yearly, 'Andere GmbH', self::QH, 1);
        $farAway = $this->entitlement('Microsoft Teams Essentials', '36.39', '2025-06-01', null, BillingFrequency::Yearly, 'Muster Bau GmbH', self::QH, 1);

        $result = (new ContractSuccessionLinker)->link([$telekom, $otherCompany, $farAway]);

        $this->assertSame([], $result['links']);
        $this->assertSame('2026-11-21', $result['entitlements'][0]->endsOn?->toDateString());
    }
}
