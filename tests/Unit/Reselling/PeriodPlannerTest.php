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

use App\Enums\Reselling\{BillingFrequency, LinkOrigin, PeriodStatus, SubscriptionStatus};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
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

        // Entscheidung an der ersten Periode (bestätigt = decided_at), dann Abo verkürzen: entschiedene bleibt, offene folgen.
        $subscription->periods()->where('starts_on', '2024-08-05')->update(['status' => PeriodStatus::Billed->value, 'decided_at' => now()]);
        $subscription->forceFill(['ends_on' => '2025-08-04', 'quantity' => 5])->save();
        $third = $planner->sync($subscription->fresh(), $reference);

        $this->assertSame(2, $third['removed'], '2025 und 2026 entfallen');
        $this->assertSame(1, $third['kept']);
        $this->assertSame(1, ResalePeriod::query()->where('subscription_id', $subscription->id)->count());
        $this->assertSame(3, ResalePeriod::query()->where('subscription_id', $subscription->id)->value('quantity'), 'berechnete Periode behält ihre Menge');
    }

    public function test_sync_lets_proposed_only_periods_follow_the_subscription_but_keeps_decided_ones(): void {
        // Review 2026-09-10 (B4): „berechnet" allein durch Vorschläge ist keine Entscheidung — Menge, Ende und
        // Preis folgen dem Abo, der Status ergibt sich neu aus der Deckung. Bestätigt (decided_at), verzichtet
        // und strittig bleiben unantastbar.
        $planner = new PeriodPlanner;
        $subscription = $this->subscription(['quantity' => 5]);
        $reference = CarbonImmutable::parse('2026-09-04');
        $planner->sync($subscription, $reference);
        [$proposed, $confirmed, $waived] = $subscription->periods()->get()->all();
        $link = static fn(ResalePeriod $p, float $months): ResalePeriodLink => ResalePeriodLink::query()->create([
            'organization_id' => $p->organization_id, 'period_id' => $p->id, 'subscription_id' => $p->subscription_id,
            'linkable_type' => \App\Models\LexofficeVoucherLine::class, 'linkable_id' => $p->id, 'voucher_number' => 'RE/' . $p->id, 'voucher_date' => $p->starts_on,
            'quantity' => 5, 'months' => $months, 'amount' => '1236.00', 'currency' => 'EUR', 'origin' => LinkOrigin::Proposed,
        ]);
        $link($proposed, 60.0);
        $proposed->forceFill(['status' => PeriodStatus::Billed])->save();
        $link($confirmed, 60.0)->forceFill(['origin' => LinkOrigin::Confirmed])->save();
        $confirmed->forceFill(['status' => PeriodStatus::Billed, 'decided_at' => now()])->save();
        $waived->forceFill(['status' => PeriodStatus::Waived, 'waived_reason' => 'Kulanz', 'decided_at' => now()])->save();

        $subscription->forceFill(['quantity' => 4])->save();
        $result = $planner->sync($subscription->fresh(), $reference);
        $this->assertSame(['created' => 0, 'updated' => 1, 'removed' => 0, 'kept' => 2], $result);
        $this->assertSame(4, $proposed->fresh()?->quantity, 'nur vorgeschlagen: folgt der Menge');
        $this->assertSame('988.80', $proposed->fresh()?->expected_sale?->getAmount(), '4 × 247,20 €');
        $this->assertSame(PeriodStatus::Billed, $proposed->fresh()?->status, '60 gedeckte Monate reichen für 48');
        $this->assertSame(5, $confirmed->fresh()?->quantity, 'bestätigt bleibt');
        $this->assertSame(5, $waived->fresh()?->quantity, 'verzichtet bleibt');

        // Mehr Lizenzen: die nur vorgeschlagene Periode wird teilweise, die bestätigte bleibt berechnet.
        $subscription->forceFill(['quantity' => 6])->save();
        $planner->sync($subscription->fresh(), $reference);
        $this->assertSame(6, $proposed->fresh()?->quantity);
        $this->assertSame(PeriodStatus::Partial, $proposed->fresh()?->status, '60 von 72');
        $this->assertSame(PeriodStatus::Billed, $confirmed->fresh()?->status);

        // Abo verkürzen: die nur vorgeschlagene Periode fällt weg, entschiedene bleiben.
        $subscription->forceFill(['ends_on' => '2024-08-04'])->save();
        $result = $planner->sync($subscription->fresh(), $reference);
        $this->assertSame(1, $result['removed']);
        $this->assertNull($proposed->fresh());
        $this->assertNotNull($confirmed->fresh());
        $this->assertNotNull($waived->fresh());
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

    public function test_monthly_stub_shorter_than_five_days_is_dropped_but_five_days_are_a_period(): void {
        // Review 2026-09-10 (G): Monatsintervall, Ende 17.03. — der Rest 15.–17.03. (3 Tage) ist Ausrichtungs-Stummel.
        $subscription = $this->subscription(['interval' => BillingFrequency::Monthly, 'term_months' => 1, 'starts_on' => '2026-01-15', 'ends_on' => '2026-03-17']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));
        $this->assertSame(['2026-01-15', '2026-02-15'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
        $this->assertSame('2026-03-14', end($planned)['ends_on']->toDateString(), 'der Stummel wird nicht an die letzte Periode angehängt');

        // Genau fünf Tage (Mindestlänge des Monatsintervalls) sind eine eigene, verkürzte Periode.
        $subscription = $this->subscription(['interval' => BillingFrequency::Monthly, 'term_months' => 1, 'starts_on' => '2026-01-15', 'ends_on' => '2026-03-19']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));
        $this->assertCount(3, $planned);
        $this->assertSame(['2026-03-15', '2026-03-19'], [$planned[2]['starts_on']->toDateString(), $planned[2]['ends_on']->toDateString()]);
    }

    public function test_superseded_subscription_without_end_plans_until_the_reference_day(): void {
        // Abgelöst, aber das Ende ist (noch) nicht bekannt: Vergangenheit bis zum Stichtag, keine Zukunft —
        // und ein Rest unter der Mindestlänge nach dem letzten Jahrestag ist Stummel.
        $superseded = $this->subscription(['status' => SubscriptionStatus::Superseded]);
        $this->assertNull($superseded->ends_on);

        $planned = (new PeriodPlanner)->plan($superseded, CarbonImmutable::parse('2026-09-04'));
        $this->assertSame(['2024-08-05', '2025-08-05', '2026-08-05'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
        $this->assertSame('2026-09-04', end($planned)['ends_on']->toDateString(), 'letzte Periode endet am Stichtag (31 Tage = Mindestlänge)');

        $planned = (new PeriodPlanner)->plan($superseded, CarbonImmutable::parse('2026-08-20'));
        $this->assertSame(['2024-08-05', '2025-08-05'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned), '16 Tage nach dem Jahrestag sind Stummel, keine Periode');

        $planned = (new PeriodPlanner)->plan($superseded, CarbonImmutable::parse('2027-12-31'));
        $this->assertSame('2027-12-31', end($planned)['ends_on']->toDateString(), 'ohne Ende wächst die Planung nur bis zum Stichtag, nie darüber hinaus');
    }

    public function test_fully_assigned_quantity_keeps_a_decided_period_but_drops_open_ones(): void {
        // Ganz abgetreten (Menge 0): offene Perioden des Vertrags entfallen (nur die Abtretung plant),
        // eine entschiedene Periode bleibt — mit Menge 0 und Soll 0, ihr Status folgt der Deckung.
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-09-04');
        $contract = $this->subscription(['quantity' => 5, 'external_id' => 'ent-5']);
        $planner->sync($contract, $reference);
        $this->assertSame(3, $contract->periods()->count());
        $decided = $contract->periods()->firstOrFail();
        $decided->forceFill(['status' => PeriodStatus::Billed, 'decided_at' => CarbonImmutable::parse('2026-01-10 10:00:00')])->save();

        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $contract->id, 'kind' => 'license', 'provider' => 'manual', 'external_id' => 'ent-5#1',
            'label' => 'Microsoft 365 Business Premium', 'quantity' => 5, 'starts_on' => '2024-08-05', 'term_months' => 12, 'interval' => BillingFrequency::Yearly,
            'renewal' => 'auto', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => SubscriptionStatus::Active,
        ]);
        $contract->refresh();
        $this->assertSame(0, $contract->billableQuantityOn(CarbonImmutable::parse('2024-08-05')));

        $result = $planner->sync($contract, $reference);
        $this->assertSame(['created' => 0, 'updated' => 1, 'removed' => 2, 'kept' => 0], $result);
        $remaining = ResalePeriod::query()->where('subscription_id', $contract->id)->get();
        $this->assertCount(1, $remaining, 'nur die entschiedene Periode bleibt');
        $this->assertSame($decided->id, $remaining[0]->id);
        $this->assertSame(0, $remaining[0]->quantity, 'Menge folgt der Abtretung');
        $this->assertSame('0.00', $remaining[0]->expected_sale?->getAmount());
        $this->assertSame(PeriodStatus::Billed, $remaining[0]->status, '0 benötigte Monate sind gedeckt');
        $this->assertNotNull($remaining[0]->decided_at, 'Entscheidung bleibt');

        // Zweiter Lauf: nichts ändert sich mehr.
        $this->assertSame(['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 1], $planner->sync($contract, $reference));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignment(ResaleSubscription $contract, array $attributes = []): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'parent_id' => $contract->id, 'kind' => 'license', 'provider' => 'manual', 'external_id' => 'ent-5#1',
            'label' => 'Microsoft 365 Business Premium', 'quantity' => 2, 'starts_on' => '2026-07-01', 'term_months' => 12, 'interval' => BillingFrequency::Yearly,
            'renewal' => 'auto', 'sale_unit_price' => '247.20', 'purchase_unit_price' => '187.92', 'currency' => 'EUR', 'status' => SubscriptionStatus::Active,
        ], $attributes));
    }

    /** @return list<array{0: string, 1: string}> */
    private function ranges(ResaleSubscription $subscription): array {
        return $subscription->periods()->get()->map(static fn(ResalePeriod $p): array => [$p->starts_on->toDateString(), $p->ends_on->toDateString()])->all();
    }

    public function test_assignment_starting_inside_a_period_is_co_termed_with_the_contract(): void {
        // Abtretung mitten in der Periode (Vertrag ×5 mit Jahresperiode 01.01.–31.12., Kind ×2 ab 01.07.): Lizenzen im
        // selben Tenant sind co-termed — die erste Periode des Kindes endet mit der laufenden Vertragsperiode (Soll
        // anteilig 6/12), danach läuft das Kind im Vertragsrhythmus. Der Vertrag behält in der laufenden Periode
        // seine 5 (vom Endkunden bezahlt); ab der nächsten Vertragsperiode gilt die Menge am Periodenbeginn (3).
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-11-01'); // Horizont 30.01.2027: die Periode 2027 ist sichtbar
        $contract = $this->subscription(['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'ent-5']);
        $planner->sync($contract, $reference);
        $child = $this->assignment($contract);

        $planned = $planner->plan($child, $reference);
        $this->assertSame([['2026-07-01', '2026-12-31'], ['2027-01-01', '2027-12-31']], array_map(static fn(array $p): array => [$p['starts_on']->toDateString(), $p['ends_on']->toDateString()], $planned));
        $this->assertSame([6, 12], [$planned[0]['months'] ?? null, $planned[0]['interval_months'] ?? null], 'Erstperiode trägt ihren Anteil');
        $this->assertArrayNotHasKey('months', $planned[1], 'ab der zweiten Periode volle Vertragsperioden');

        $planner->sync($child, $reference);
        $contract->refresh();
        $result = $planner->sync($contract, $reference);

        $this->assertSame(['2026-07-01', '2026-12-31'], $this->ranges($child)[0]);
        [$first, $second] = $child->periods()->get()->all();
        $this->assertSame('247.20', $first->expected_sale?->getAmount(), '2 × 247,20 × 6/12');
        $this->assertSame('187.92', $first->expected_purchase?->getAmount(), '2 × 187,92 × 6/12');
        $this->assertSame(6, $first->termMonths());
        $this->assertSame(12.0, $first->requiredMonths(), 'Menge × tatsächliche Monate');
        $this->assertSame('494.40', $second->expected_sale?->getAmount(), 'volle Vertragsperiode 2027');
        $this->assertSame(24.0, $second->requiredMonths());

        $this->assertSame(1, $result['updated'], 'nur 2027 folgt der Abtretung');
        $this->assertSame([5, 3], $contract->periods()->pluck('quantity')->all(), 'laufende Periode behält 5, ab 2027 Rest 3');
        $this->assertSame(['1236.00', '741.60'], $contract->periods()->get()->map(static fn(ResalePeriod $p): ?string => $p->expected_sale?->getAmount())->all());
        $this->assertSame(5, $contract->billableQuantityOn(CarbonImmutable::parse('2026-06-30')));
        $this->assertSame(3, $contract->billableQuantityOn(CarbonImmutable::parse('2026-07-01')));

        // Zweiter Lauf: idempotent, das anteilige Soll gilt nicht als Änderung.
        $this->assertSame(['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 2], $planner->sync($child, $reference));
    }

    public function test_assignment_starting_at_the_period_boundary_plans_full_contract_periods(): void {
        // Abtretung genau am Periodenanfang: wie bisher — volle Perioden, volles Soll, der Vertrag plant ab da mit dem Rest.
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-11-01');
        $contract = $this->subscription(['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'ent-5']);
        $planner->sync($contract, $reference);
        $child = $this->assignment($contract, ['starts_on' => '2027-01-01']);

        $planned = $planner->plan($child, $reference);
        $this->assertSame([['2027-01-01', '2027-12-31']], array_map(static fn(array $p): array => [$p['starts_on']->toDateString(), $p['ends_on']->toDateString()], $planned));
        $this->assertArrayNotHasKey('months', $planned[0]);

        $planner->sync($child, $reference);
        $contract->refresh();
        $planner->sync($contract, $reference);
        $this->assertSame('494.40', $child->periods()->first()?->expected_sale?->getAmount(), '2 × 247,20 ungekürzt');
        $this->assertSame([5, 3], $contract->periods()->pluck('quantity')->all());
    }

    public function test_assignment_on_a_monthly_contract_follows_the_monthly_boundaries(): void {
        // Monatsintervall: Rest des laufenden Monats ist die Erstperiode (mind. 5 Tage), danach die Monatsgrenzen des Vertrags.
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-09-04');
        $contract = $this->subscription(['quantity' => 5, 'starts_on' => '2026-01-01', 'interval' => BillingFrequency::Monthly, 'term_months' => 1, 'external_id' => 'ent-5']);
        $child = $this->assignment($contract, ['starts_on' => '2026-07-15', 'interval' => BillingFrequency::Monthly, 'term_months' => 1, 'sale_unit_price' => '20.60']);

        $planned = $planner->plan($child, $reference);
        $this->assertSame(['2026-07-15', '2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01'], array_map(static fn(array $p): string => $p['starts_on']->toDateString(), $planned));
        $this->assertSame('2026-07-31', $planned[0]['ends_on']->toDateString());
        $this->assertSame([1, 1], [$planned[0]['months'] ?? null, $planned[0]['interval_months'] ?? null], 'ein angebrochener Monat zählt als Monat');

        $planner->sync($child, $reference);
        $this->assertSame('41.20', $child->periods()->first()?->expected_sale?->getAmount(), 'Monatspreis ungekürzt: 2 × 20,60');

        // Rest unter der Mindestlänge (29.–31.07. = 3 Tage): beim Vertrag bezahlt, die Abtretung beginnt mit dem August.
        $stub = $this->assignment($contract, ['starts_on' => '2026-07-29', 'interval' => BillingFrequency::Monthly, 'term_months' => 1, 'external_id' => 'ent-5#2']);
        $planned = $planner->plan($stub, $reference);
        $this->assertSame('2026-08-01', $planned[0]['starts_on']->toDateString());
        $this->assertArrayNotHasKey('months', $planned[0]);
    }

    public function test_assignment_ending_before_the_contract_period_end_is_a_single_prorated_period(): void {
        // Kind endet vor dem Vertragsperiodenende (01.07.–30.09.): eine Co-Term-Periode über drei Monate, Soll 3/12 —
        // und der Vertrag hat ab 2027 wieder alle fünf Lizenzen.
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-11-01');
        $contract = $this->subscription(['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'ent-5']);
        $planner->sync($contract, $reference);
        $child = $this->assignment($contract, ['ends_on' => '2026-09-30']);

        $planned = $planner->plan($child, $reference);
        $this->assertSame([['2026-07-01', '2026-09-30']], array_map(static fn(array $p): array => [$p['starts_on']->toDateString(), $p['ends_on']->toDateString()], $planned));
        $this->assertSame(3, $planned[0]['months'] ?? null);

        $planner->sync($child, $reference);
        $contract->refresh();
        $planner->sync($contract, $reference);
        $this->assertSame('123.60', $child->periods()->first()?->expected_sale?->getAmount(), '2 × 247,20 × 3/12');
        $this->assertSame(6.0, $child->periods()->first()?->requiredMonths());
        $this->assertSame([5, 5], $contract->periods()->pluck('quantity')->all(), 'nach dem Ende der Abtretung wieder voll');

        // Kind über den Vertragsjahreswechsel, aber vor dem nächsten Periodenende (01.07.2026–30.06.2027): zwei Perioden,
        // die zweite an der Vertragsgrenze beginnend und vom Kind-Ende gekürzt.
        $spanning = $this->assignment($contract, ['ends_on' => '2027-06-30', 'external_id' => 'ent-5#2']);
        $planned = $planner->plan($spanning, $reference);
        $this->assertSame([['2026-07-01', '2026-12-31'], ['2027-01-01', '2027-06-30']], array_map(static fn(array $p): array => [$p['starts_on']->toDateString(), $p['ends_on']->toDateString()], $planned));
    }

    public function test_assignment_without_contract_keeps_its_own_rhythm(): void {
        // Vertrag gelöscht (FK nullOnDelete) oder Vertragsbezug ins Leere: die Abtretung plant wie ein
        // eigenständiges Abo ab ihrem Beginn — ebenso, wenn der Vertrag erst nach ihr beginnt (Datenfehler).
        $planner = new PeriodPlanner;
        $reference = CarbonImmutable::parse('2026-11-01');
        $expected = [['2026-07-01', '2027-06-30']];
        $ranges = static fn(array $planned): array => array_map(static fn(array $p): array => [$p['starts_on']->toDateString(), $p['ends_on']->toDateString()], $planned);

        $contract = $this->subscription(['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'ent-5']);
        $child = $this->assignment($contract);
        $child->setRelation('parent', null);
        $planned = $planner->plan($child, $reference);
        $this->assertSame($expected, $ranges($planned));
        $this->assertArrayNotHasKey('months', $planned[0]);

        $contract->delete();
        $child->refresh();
        $this->assertNull($child->parent_id, 'FK nullOnDelete');
        $this->assertSame($expected, $ranges($planner->plan($child, $reference)));

        $late = $this->subscription(['quantity' => 5, 'starts_on' => '2026-09-01', 'external_id' => 'ent-6']);
        $this->assertSame($expected, $ranges($planner->plan($this->assignment($late, ['external_id' => 'ent-6#1']), $reference)), 'Vertrag beginnt nach der Abtretung: eigener Rhythmus');
    }

    public function test_planning_is_capped_at_max_periods(): void {
        // Sehr lange Laufzeit mit Monatsintervall: die Planung bricht bei MAX_PERIODS ab statt Tausende Zeilen anzulegen.
        $max = (new \ReflectionClassConstant(PeriodPlanner::class, 'MAX_PERIODS'))->getValue();
        $this->assertIsInt($max);
        $subscription = $this->subscription(['interval' => BillingFrequency::Monthly, 'term_months' => 1, 'starts_on' => '1950-01-01']);
        $planned = (new PeriodPlanner)->plan($subscription, CarbonImmutable::parse('2026-09-04'));

        $this->assertCount($max, $planned);
        $this->assertSame('1950-01-01', $planned[0]['starts_on']->toDateString());
        $this->assertSame(CarbonImmutable::parse('1950-01-01')->addMonthsNoOverflow($max - 1)->toDateString(), end($planned)['starts_on']->toDateString(), 'Kappung am Anfang der Laufzeit, nicht am Horizont');
        $this->assertTrue(end($planned)['ends_on']->lessThan(CarbonImmutable::parse('2026-09-04')), 'die Gegenwart wird durch die Kappung nicht erreicht');
    }
}
