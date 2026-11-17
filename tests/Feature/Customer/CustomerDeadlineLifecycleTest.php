<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Customer\CustomerDeadlineStatus;
use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets, CustomerDeadlineService};
use App\Services\Customer\Deadlines\RuleSets\{HomeCareDeadlineRuleSet, TaxAdvisoryDeadlineRuleSet};
use App\Services\Diary\{FollowUpPeriodService, OrderService};
use App\Support\Period\PeriodKey;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lebenslauf einer Kundenfrist (Feature 197, MVP-1632): Übergänge nur über
 * den Statusvertrag, Verlauf im Journal, Erledigung über `OrderCompleted`
 * (Abnahme 6) und die Fälligkeit der Folgeperiode (Feature 198, MVP-1637).
 */
final class CustomerDeadlineLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Erika Beispiel']);
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY, HomeCareDeadlineRuleSet::KEY]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function careClient(): void {
        $grade = Classification::factory()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::Pflegegrad->value, 'code' => 'pg3', 'label' => 'Pflegegrad 3']);
        $this->customer->classifications()->attach($grade->id);
        $ruleSet = app(CustomerDeadlineRuleSets::class)->get($this->organization, HomeCareDeadlineRuleSet::KEY);
        $this->assertNotNull($ruleSet);
        app(CustomerDeadlineProfileService::class)->save($this->customer, $ruleSet, ['benefit' => 'care_allowance'], CarbonImmutable::parse('2026-01-01'), $this->admin, $this->admin);
    }

    private function visit(string $period): CustomerDeadline {
        return CustomerDeadline::query()->where('customer_id', $this->customer->id)->where('kind', HomeCareDeadlineRuleSet::KIND)->where('period_key', $period)->sole();
    }

    private function order(string $slug, string $period, ?string $productGroup = null): DiaryEntry {
        $type = EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => $slug, 'label' => ucfirst($slug), 'requires_customer' => true]);
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'assigned_user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'entry_type_id' => $type->id,
            'period_key' => PeriodKey::parse($period),
            'status' => Status::InProgress,
        ]);
        if ($productGroup !== null) {
            Classification::factory()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::ProductGroup->value, 'code' => $productGroup, 'label' => ucfirst($productGroup)]);
            $entry->assignProductGroup($productGroup);
        }

        return $entry->refresh();
    }

    /** Abnahme 6: ein abgeschlossener Auftrag `beratungsbesuch` 2026-H1 erledigt die Frist des ersten Halbjahrs. */
    public function test_a_completed_order_with_period_completes_the_matching_deadline(): void {
        $this->careClient();
        $entry = $this->order('beratungsbesuch', '2026-H1');

        app(OrderService::class)->complete($entry, $this->admin, 'Beratung durchgeführt');

        $h1 = $this->visit('2026-H1');
        $this->assertSame(CustomerDeadlineStatus::Completed, $h1->status);
        $this->assertSame((int) $entry->id, (int) $h1->diary_entry_id);
        $this->assertSame(CustomerDeadlineStatus::Open, $this->visit('2026-H2')->status);
        $this->assertContains('deadline_completed_by_order', $h1->journal()->pluck('event')->all());
    }

    /** Andere Eintragsart oder Periode erledigt nichts. */
    public function test_an_unrelated_order_leaves_the_deadline_open(): void {
        $this->careClient();
        app(OrderService::class)->complete($this->order('pflegevisite', '2026-H1'), $this->admin, 'Visite');
        app(OrderService::class)->complete($this->order('beratungsbesuch', '2026-H2'), $this->admin, 'zu früh, aber andere Periode');

        $this->assertSame(CustomerDeadlineStatus::Open, $this->visit('2026-H1')->status);
        $this->assertSame(CustomerDeadlineStatus::Completed, $this->visit('2026-H2')->status);
    }

    /** Statusvertrag: verlängern mit Datum und Grund, erledigen, wieder öffnen — alles im Verlauf. */
    public function test_transitions_follow_the_status_contract_and_are_journaled(): void {
        $this->careClient();
        $service = app(CustomerDeadlineService::class);
        $deadline = $this->visit('2026-H1');

        $deadline = $service->extend($deadline, CarbonImmutable::parse('2026-07-31'), 'Krankenhausaufenthalt', $this->admin);
        $this->assertSame(CustomerDeadlineStatus::Extended, $deadline->status);
        $this->assertSame('2026-07-31', $deadline->due_on->toDateString());
        $this->assertSame('2026-06-30', $deadline->original_due_on->toDateString());

        $deadline = $service->complete($deadline, $this->admin);
        $this->assertSame(CustomerDeadlineStatus::Completed, $deadline->status);
        $this->assertNotNull($deadline->completed_at);

        try {
            $service->waive($deadline, 'falsch', $this->admin);
            $this->fail('Erledigt → entfällt ist kein erlaubter Übergang.');
        } catch (ValidationException) {
            $this->assertSame(CustomerDeadlineStatus::Completed, $deadline->refresh()->status);
        }

        $deadline = $service->reopen($deadline, 'Versehen', $this->admin);
        $this->assertSame(CustomerDeadlineStatus::Open, $deadline->status);
        $this->assertNull($deadline->completed_at);
        $this->assertSame(
            ['deadline_created', 'deadline_extended', 'deadline_completed', 'deadline_reopened'],
            $deadline->journal()->pluck('event')->all(),
        );
    }

    /** Feature 198, MVP-1637: die Folgeperiode bekommt ihre Fälligkeit aus der Kundenfrist gleicher Art und Periode. */
    public function test_the_follow_up_period_takes_its_due_date_from_the_customer_deadline(): void {
        $this->careClient();
        $entry = $this->order('beratungsbesuch', '2026-H1');
        $followUps = app(FollowUpPeriodService::class);

        $this->assertSame('2026-12-31', $followUps->dueDateFor($entry, PeriodKey::of(PeriodKind::HalfYear, 2026, 2))?->toDateString());
        $this->assertNull($followUps->dueDateFor($entry, PeriodKey::of(PeriodKind::HalfYear, 2030, 1)), 'ohne Frist bleibt die Fälligkeit leer');

        $next = $followUps->create($entry, $this->admin);
        $this->assertSame('2026-12-31', $next->due_date?->toDateString());
    }

    /** Lohn erledigt Lohnsteuer-Anmeldung, Beitragsnachweis und Fälligkeit desselben Monats; die Folgeperiode nimmt den frühesten Termin. */
    public function test_payroll_orders_complete_wage_tax_and_social_security(): void {
        $type = EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'lohn', 'label' => 'Lohn']);
        \App\Models\Customer\CustomerService::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'entry_type_id' => $type->id, 'period_kind' => PeriodKind::Month, 'valid_from' => '2026-01-01']);
        $ruleSet = app(CustomerDeadlineRuleSets::class)->get($this->organization, TaxAdvisoryDeadlineRuleSet::KEY);
        $this->assertNotNull($ruleSet);
        app(CustomerDeadlineProfileService::class)->save($this->customer, $ruleSet, ['vat_interval' => 'none', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin'], CarbonImmutable::parse('2026-01-01'), $this->admin, $this->admin);

        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'assigned_user_id' => $this->admin->id,
            'customer_id' => $this->customer->id, 'entry_type_id' => $type->id, 'period_key' => PeriodKey::parse('2026-03'), 'status' => Status::InProgress,
        ]);
        $this->assertSame('2026-04-24', app(FollowUpPeriodService::class)->dueDateFor($entry, PeriodKey::parse('2026-04'))?->toDateString(), 'Beitragsnachweis April vor der Lohnsteuer-Anmeldung');

        app(OrderService::class)->complete($entry, $this->admin, 'Lohn März');

        $done = CustomerDeadline::query()->where('customer_id', $this->customer->id)->where('period_key', '2026-03')->pluck('status', 'kind')->map(static fn ($s) => $s->value)->all();
        $this->assertSame(['contribution_due' => 'completed', 'contribution_statement' => 'completed', 'wage_tax' => 'completed'], collect($done)->sortKeys()->all());
    }
}
