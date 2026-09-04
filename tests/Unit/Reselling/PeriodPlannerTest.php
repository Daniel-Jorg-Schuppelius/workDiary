<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodPlannerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\{BillingFrequency, PeriodStatus, SubscriptionStatus};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Register\PeriodPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Periodenplanung des Reselling-Registers (Feature 152, MVP-758).
 */
class PeriodPlannerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes = []): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'kind' => 'license',
            'provider' => 'manual',
            'label' => 'Microsoft 365 Business Premium',
            'quantity' => 3,
            'starts_on' => '2024-08-05',
            'term_months' => 12,
            'interval' => BillingFrequency::Yearly,
            'renewal' => 'auto',
            'sale_unit_price' => '247.20',
            'purchase_unit_price' => '187.92',
            'currency' => 'EUR',
            'status' => SubscriptionStatus::Active,
        ], $attributes));
    }

    public function test_open_ended_yearly_subscription_plans_until_the_horizon(): void {
        $subscription = $this->subscription();
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));

        // 05.08.24, 05.08.25, 05.08.26 — der 05.08.27 liegt jenseits der 90 Tage.
        $this->assertSame(['2024-08-05', '2025-08-05', '2026-08-05'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
        $this->assertSame('2025-08-04', $planned[0]['ends_on']->toDateString());
    }

    public function test_upcoming_period_within_horizon_is_planned(): void {
        $subscription = $this->subscription(['starts_on' => '2023-11-15']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));

        $this->assertSame('2026-11-15', end($planned)['starts_on']->toDateString(), 'Verlängerung in 72 Tagen ist sichtbar');
    }

    public function test_fixed_end_drops_the_alignment_stub(): void {
        // Endet 20 Tage nach dem zweiten Jahrestag: der Rest ist Co-Term-Stummel.
        $subscription = $this->subscription(['ends_on' => '2026-08-24']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2027-01-01'));

        $this->assertCount(2, $planned);
        $this->assertSame('2026-08-04', $planned[1]['ends_on']->toDateString());
    }

    public function test_fixed_end_inside_a_period_shortens_the_last_period(): void {
        $subscription = $this->subscription(['ends_on' => '2026-02-28']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2027-01-01'));

        $this->assertCount(2, $planned);
        $this->assertSame('2026-02-28', $planned[1]['ends_on']->toDateString(), 'letzter Tag inklusiv');
    }

    public function test_monthly_subscription_plans_month_periods(): void {
        $subscription = $this->subscription(['interval' => BillingFrequency::Monthly, 'starts_on' => '2026-06-30', 'term_months' => 1]);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));

        $this->assertSame(['2026-06-30', '2026-07-30', '2026-08-30', '2026-09-30', '2026-10-30', '2026-11-30'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
    }

    public function test_ended_subscription_keeps_its_past_but_does_not_grow(): void {
        // Abgelöst am 05.08.2025 (Telekom → Quality Hosting): die Periode 2024 bleibt prüfbar, 2025 gehört dem Nachfolger.
        $superseded = $this->subscription(['status' => SubscriptionStatus::Superseded, 'ends_on' => '2025-08-05']);
        $planned = (new PeriodPlanner)->plan($superseded, CarbonImmutable::parse('2026-09-04'));
        $this->assertSame(['2024-08-05'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));

        // Beendet ohne bekanntes Ende: Vergangenheit bis zum Stichtag, keine Zukunft.
        $ended = $this->subscription(['status' => SubscriptionStatus::Ended]);
        $planned = (new PeriodPlanner)->plan($ended, CarbonImmutable::parse('2026-09-04'));
        $this->assertSame(['2024-08-05', '2025-08-05', '2026-08-05'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
        $this->assertSame('2026-09-04', end($planned)['ends_on']->toDateString());
    }

    public function test_sync_is_idempotent_and_keeps_decided_periods(): void {
        $planner = new PeriodPlanner;
        $subscription = $this->subscription();
        $reference = CarbonImmutable::parse('2026-09-04');

        $first = $planner->sync($subscription, $reference);
        $this->assertSame(3, $first['created']);
        $this->assertSame('741.60', $subscription->periods()->first()?->expected_sale?->getAmount(), '3 × 247,20 €');
        $this->assertSame('563.76', $subscription->periods()->first()?->expected_purchase?->getAmount());

        $second = $planner->sync($subscription, $reference);
        $this->assertSame(['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 3], $second);

        // Entscheidung an der ersten Periode, dann Abo verkürzen: entschiedene bleibt, offene folgen.
        $subscription->periods()->where('starts_on', '2024-08-05')->update(['status' => PeriodStatus::Billed->value]);
        $subscription->forceFill(['ends_on' => '2025-08-04', 'quantity' => 5])->save();
        $third = $planner->sync($subscription->fresh(), $reference);

        $this->assertSame(2, $third['removed'], '2025 und 2026 entfallen');
        $this->assertSame(1, $third['kept']);
        $this->assertSame(1, ResalePeriod::query()->where('subscription_id', $subscription->id)->count());
        $this->assertSame(3, ResalePeriod::query()->where('subscription_id', $subscription->id)->value('quantity'), 'berechnete Periode behält ihre Menge');
    }

    public function test_sync_updates_open_periods_after_quantity_change(): void {
        $planner = new PeriodPlanner;
        $subscription = $this->subscription();
        $reference = CarbonImmutable::parse('2026-09-04');
        $planner->sync($subscription, $reference);

        $subscription->forceFill(['quantity' => 4])->save();
        $result = $planner->sync($subscription->fresh(), $reference);

        $this->assertSame(3, $result['updated']);
        $this->assertSame('988.80', $subscription->periods()->first()?->expected_sale?->getAmount(), '4 × 247,20 €');
    }
}
