<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingPeriodExpanderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\{BillingPeriodExpander, UnitPriceCatalog};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BillingPeriodExpanderTest extends TestCase {
    use BuildsEntitlements;

    public function test_two_year_term_yields_two_annual_periods_with_inclusive_ends(): void {
        $entitlement = $this->entitlement('Microsoft 365 Business Premium', '244.76', '2024-08-02', '2026-08-02');
        $periods = $this->expander([$entitlement])->all($entitlement);

        $this->assertCount(2, $periods);
        $this->assertSame('2024-08-02', $periods[0]->startsOn->toDateString());
        $this->assertSame('2025-08-01', $periods[0]->endsOn->toDateString());
        $this->assertSame('2025-08-02', $periods[1]->startsOn->toDateString());
        $this->assertSame('2026-08-01', $periods[1]->endsOn->toDateString());
        $this->assertSame(2, $periods[1]->index);
        $this->assertSame(1, $periods[0]->quantity);
        $this->assertSame(24476, $periods[0]->unitFee->getMinorAmount());
    }

    public function test_alignment_stub_at_term_end_is_not_a_period(): void {
        // Marketplace richtet Laufzeiten am Jahrestag der Erstbestellung aus:
        // 28.03.23 → 02.04.27 hat vier volle Jahre plus fünf Tage Stummel.
        $entitlement = $this->entitlement('Exchange Online (Plan 1)', '41.55', '2023-03-28', '2027-04-02');
        $periods = $this->expander([$entitlement])->all($entitlement);

        $this->assertCount(4, $periods);
        $this->assertSame('2027-03-27', $periods[3]->endsOn->toDateString());
    }

    public function test_short_remainder_above_threshold_is_kept_and_capped_at_term_end(): void {
        $entitlement = $this->entitlement('Exchange Online (Plan 1)', '41.55', '2023-03-28', '2024-06-15');
        $periods = $this->expander([$entitlement])->all($entitlement);

        $this->assertCount(2, $periods);
        $this->assertSame('2024-03-28', $periods[1]->startsOn->toDateString());
        $this->assertSame('2024-06-14', $periods[1]->endsOn->toDateString());
    }

    public function test_due_until_filters_by_period_start(): void {
        $entitlement = $this->entitlement('Microsoft 365 Business Premium', '244.76', '2024-08-02', '2027-08-02');
        $expander = $this->expander([$entitlement]);

        $this->assertCount(3, $expander->all($entitlement));
        $this->assertCount(1, $expander->dueUntil($entitlement, CarbonImmutable::parse('2025-08-01')));
        $this->assertCount(2, $expander->dueUntil($entitlement, CarbonImmutable::parse('2025-08-02')));
        $this->assertCount(0, $expander->dueUntil($entitlement, CarbonImmutable::parse('2024-08-01')));
    }

    public function test_monthly_frequency_steps_by_month(): void {
        $entitlement = $this->entitlement('Microsoft Teams Essentials', '3.90', '2025-01-31', '2025-05-01', BillingFrequency::Monthly);
        $periods = $this->expander([$entitlement])->all($entitlement);

        // 31.01 → 28.02 → 28.03 → 28.04; der Rest 28.04–30.04 (3 Tage) ist ein Stummel.
        $this->assertCount(3, $periods);
        $this->assertSame('2025-02-28', $periods[1]->startsOn->toDateString(), 'Monatsende ohne Überlauf');
        $this->assertSame('2025-04-27', $periods[2]->endsOn->toDateString());
    }

    public function test_open_ended_contract_expands_up_to_reference_only(): void {
        $entitlement = $this->entitlement('Microsoft 365 Business Standard', '115.22', '2025-08-03', null, BillingFrequency::Yearly, 'Muster Bau GmbH', \App\Services\Reselling\Marketplace\MarketplaceEntitlement::SOURCE_QUALITYHOSTING, 1);
        $expander = $this->expander([$entitlement]);

        $periods = $expander->dueUntil($entitlement, CarbonImmutable::parse('2026-09-03'));
        $this->assertCount(2, $periods);
        $this->assertSame('2026-08-03', $periods[1]->startsOn->toDateString());
        $this->assertSame('2027-08-02', $periods[1]->endsOn->toDateString());
        $this->assertSame(11522, $periods[1]->unitFee->getMinorAmount(), 'Stückpreis aus der Quelle, nicht abgeleitet');

        $this->expectException(\LogicException::class);
        $expander->all($entitlement);
    }

    public function test_explicit_quantity_beats_derivation(): void {
        $entitlement = $this->entitlement('Microsoft 365 Business Premium', '1503.36', '2025-08-02', null, BillingFrequency::Yearly, 'Muster Bau GmbH', \App\Services\Reselling\Marketplace\MarketplaceEntitlement::SOURCE_QUALITYHOSTING, 8);
        $periods = $this->expander([$entitlement])->dueUntil($entitlement, CarbonImmutable::parse('2025-12-31'));

        $this->assertCount(1, $periods);
        $this->assertSame(8, $periods[0]->quantity);
        $this->assertSame(18792, $periods[0]->unitFee->getMinorAmount());
    }

    /**
     * @param  list<\App\Services\Reselling\Marketplace\MarketplaceEntitlement>  $entitlements
     */
    private function expander(array $entitlements): BillingPeriodExpander {
        return new BillingPeriodExpander(UnitPriceCatalog::fromEntitlements($entitlements));
    }
}
