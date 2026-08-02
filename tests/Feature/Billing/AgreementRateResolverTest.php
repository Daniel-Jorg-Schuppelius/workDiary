<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementRateResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\ActivityCategory;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Kunden-Sonderkonditionen fließen als eigene Stufe in die
 * RateCalculator-Hierarchie (Entry-Override → Kondition → User → …) und
 * snapshotten sich inkl. customer_billing_rate_id am Zeiteintrag.
 * 2026-07-18 = Samstag, 2026-07-19 = Sonntag (Europe/Berlin).
 */
class AgreementRateResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CustomerBillingAgreement $agreement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'workdays_per_week' => 6,
        ]);
    }

    private function rate(float $hourly, string $dayType = 'weekday', ?int $categoryId = null): CustomerBillingRate {
        return CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'activity_category_id' => $categoryId,
            'day_type' => $dayType,
            'hourly_rate' => $hourly,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeEntry(string $startLocalDay, array $attributes = []): TimeEntry {
        // 10:00 UTC = 12:00 lokal (CEST) — Tagtyp ist eindeutig.
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => $startLocalDay . ' 10:00:00',
            'ended_at' => $startLocalDay . ' 12:00:00',
        ], $attributes));
    }

    public function test_weekday_and_weekend_rates_apply_by_agreement_definition(): void {
        $weekday = $this->rate(16.50);
        $weekend = $this->rate(17.50, 'weekend');

        $saturday = $this->makeEntry('2026-07-18')->fresh();
        $sunday = $this->makeEntry('2026-07-19')->fresh();

        // workdays_per_week=6 ⇒ Samstag ist Werktag, nur Sonntag Wochenende.
        $this->assertSame('16.50', $saturday->hourly_rate?->getAmount());
        $this->assertSame($weekday->id, $saturday->customer_billing_rate_id);
        $this->assertSame('33.00', $saturday->rate?->getAmount());

        $this->assertSame('17.50', $sunday->hourly_rate?->getAmount());
        $this->assertSame($weekend->id, $sunday->customer_billing_rate_id);
        $this->assertSame('35.00', $sunday->rate?->getAmount());
    }

    public function test_five_workdays_make_saturday_a_weekend(): void {
        $this->agreement->update(['workdays_per_week' => 5]);
        $this->rate(16.50);
        $this->rate(17.50, 'weekend');

        $saturday = $this->makeEntry('2026-07-18')->fresh();

        $this->assertSame('17.50', $saturday->hourly_rate?->getAmount());
    }

    public function test_category_rate_beats_category_fallback(): void {
        $category = ActivityCategory::factory()->create(['organization_id' => $this->organization->id]);
        $this->rate(16.50);
        $categoryRate = $this->rate(20.00, 'weekday', $category->id);

        $entry = $this->makeEntry('2026-07-17', ['activity_category_id' => $category->id])->fresh();

        $this->assertSame('20.00', $entry->hourly_rate?->getAmount());
        $this->assertSame($categoryRate->id, $entry->customer_billing_rate_id);
    }

    public function test_missing_weekend_rate_falls_back_to_weekday_rate(): void {
        $weekday = $this->rate(16.50);

        $sunday = $this->makeEntry('2026-07-19')->fresh();

        $this->assertSame('16.50', $sunday->hourly_rate?->getAmount());
        $this->assertSame($weekday->id, $sunday->customer_billing_rate_id);
    }

    public function test_entry_override_beats_agreement_and_clears_marker(): void {
        $this->rate(16.50);

        $entry = $this->makeEntry('2026-07-17', ['hourly_rate' => 50.00])->fresh();

        $this->assertSame('50.00', $entry->hourly_rate?->getAmount());
        $this->assertNull($entry->customer_billing_rate_id);
    }

    public function test_agreement_beats_user_rate(): void {
        $this->user->update(['hourly_rate' => 99.00]);
        $this->rate(16.50);

        $entry = $this->makeEntry('2026-07-17')->fresh();

        $this->assertSame('16.50', $entry->hourly_rate?->getAmount());
    }

    public function test_redating_saturday_to_sunday_reapplies_weekend_rate(): void {
        $this->rate(16.50);
        $weekend = $this->rate(17.50, 'weekend');

        $entry = $this->makeEntry('2026-07-18')->fresh();
        $this->assertSame('16.50', $entry->hourly_rate?->getAmount());

        $entry->update([
            'started_at' => '2026-07-19 10:00:00',
            'ended_at' => '2026-07-19 12:00:00',
            'date' => null,
        ]);
        $entry->refresh();

        $this->assertSame('17.50', $entry->hourly_rate?->getAmount());
        $this->assertSame($weekend->id, $entry->customer_billing_rate_id);
        $this->assertSame('35.00', $entry->rate?->getAmount());
    }

    public function test_manual_override_survives_redating(): void {
        $this->rate(16.50);
        $this->rate(17.50, 'weekend');

        $entry = $this->makeEntry('2026-07-18', ['hourly_rate' => 50.00])->fresh();

        $entry->update([
            'started_at' => '2026-07-19 10:00:00',
            'ended_at' => '2026-07-19 12:00:00',
            'date' => null,
        ]);
        $entry->refresh();

        $this->assertSame('50.00', $entry->hourly_rate?->getAmount());
        $this->assertNull($entry->customer_billing_rate_id);
    }

    public function test_travel_flat_adds_billable_minutes_without_touching_worked_time(): void {
        $this->rate(16.50);
        $this->agreement->update(['travel_minutes_per_entry' => 20]);

        $entry = $this->makeEntry('2026-07-17')->fresh();

        // 2 h Arbeit + 20 Min. Anfahrt à 16,50 € = 38,50 €; minutes bleibt 120.
        $this->assertSame(120, $entry->minutes);
        $this->assertSame(20, $entry->billing_travel_minutes);
        $this->assertSame('38.50', $entry->rate?->getAmount());
    }

    public function test_travel_flat_follows_the_weekend_rate(): void {
        $this->rate(16.50);
        $this->rate(17.50, 'weekend');
        $this->agreement->update(['travel_minutes_per_entry' => 20]);

        $sunday = $this->makeEntry('2026-07-19')->fresh();

        $this->assertSame('40.83', $sunday->rate?->getAmount());
    }

    public function test_travel_flat_applies_only_to_selected_categories(): void {
        $category = ActivityCategory::factory()->create(['organization_id' => $this->organization->id]);
        $this->rate(16.50);
        $this->agreement->update([
            'travel_minutes_per_entry' => 20,
            'travel_categories' => [$category->id],
        ]);

        $matching = $this->makeEntry('2026-07-17', ['activity_category_id' => $category->id])->fresh();
        $other = $this->makeEntry('2026-07-17')->fresh();

        $this->assertSame(20, $matching->billing_travel_minutes);
        $this->assertSame(0, $other->billing_travel_minutes);
        $this->assertSame('33.00', $other->rate?->getAmount());
    }

    public function test_travel_flat_skips_non_work_and_fixed_price_entries(): void {
        $this->rate(16.50);
        $this->agreement->update(['travel_minutes_per_entry' => 20]);

        $travel = $this->makeEntry('2026-07-17', ['kind' => TimeEntryKind::Travel->value])->fresh();
        $fixed = $this->makeEntry('2026-07-17', ['fixed_rate' => 100.00])->fresh();
        $unbillable = $this->makeEntry('2026-07-17', ['billable' => false])->fresh();

        $this->assertSame(0, $travel->billing_travel_minutes);
        $this->assertSame(0, $fixed->billing_travel_minutes);
        $this->assertSame('100.00', $fixed->rate?->getAmount());
        $this->assertSame(0, $unbillable->billing_travel_minutes);
    }

    public function test_manual_travel_value_beats_the_agreement(): void {
        $this->rate(16.50);
        $this->agreement->update(['travel_minutes_per_entry' => 20]);

        $entry = $this->makeEntry('2026-07-17', [
            'billing_travel_minutes' => 5,
            'billing_travel_manual' => true,
        ])->fresh();

        // 125 Min. à 16,50 € = 34,38 €.
        $this->assertSame(5, $entry->billing_travel_minutes);
        $this->assertSame('34.38', $entry->rate?->getAmount());
    }

    public function test_holiday_counts_as_weekend_only_when_enabled(): void {
        $this->rate(16.50);
        $this->rate(17.50, 'weekend');

        // 2026-05-01 (Tag der Arbeit) ist ein Freitag — ohne Schalter Werktag.
        $withoutFlag = $this->makeEntry('2026-05-01')->fresh();
        $this->assertSame('16.50', $withoutFlag->hourly_rate?->getAmount());

        $this->agreement->update(['holidays_as_weekend' => true]);
        $withFlag = $this->makeEntry('2026-05-01')->fresh();

        $this->assertSame('17.50', $withFlag->hourly_rate?->getAmount());
    }

    public function test_inactive_agreement_uses_normal_hierarchy(): void {
        $this->rate(16.50);
        $this->agreement->update(['active' => false]);
        $this->customer->update(['hourly_rate' => 80.00]);

        $entry = $this->makeEntry('2026-07-17')->fresh();

        $this->assertSame('80.00', $entry->hourly_rate?->getAmount());
        $this->assertNull($entry->customer_billing_rate_id);
    }
}
