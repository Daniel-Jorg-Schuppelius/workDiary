<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardCarriersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CarryForward;

use App\Enums\AssetCompliance\AssetInspectionScheduleStatus;
use App\Enums\Classification\ClassificationDomain;
use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractObligationStatus, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Enums\Diary\LocationMode;
use App\Enums\Period\PeriodKind;
use App\Enums\Recurrence\RecurrenceFrequency;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile, AssetInspectionOrder, AssetInspectionOrderItem, AssetInspectionSchedule};
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Project\{Project, RecurrenceRule};
use App\Models\Supplier\Supplier;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\CarryForward\CarryForwardNotes;
use App\Services\Contract\ContractService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Weitere Träger der Merker für die nächste Periode (Feature 201, MVP-1646):
 * wiederkehrende Vertragspflichten, wiederkehrende Aufträge eines Projekts,
 * Prüfpflichten der Prüfmittel — und die offenen Merker im Cockpit.
 */
final class CarryForwardCarriersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private CarryForwardNotes $notes;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->notes = app(CarryForwardNotes::class);
    }

    private function contract(?Customer $customer = null): Contract {
        return app(ContractService::class)->create($this->organization, $this->admin, [
            'title' => 'Wartungsvertrag Beispielhaus',
            'kind' => ContractKind::Maintenance->value,
            'status' => ContractStatus::Draft->value,
            'partner_type' => $customer !== null ? ContractPartnerType::Customer->value : ContractPartnerType::Other->value,
            'customer_id' => $customer?->id,
            'partner_name' => 'Musterfirma GmbH',
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-01-01',
            'notice_period_days' => 90,
            'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR',
            'value_period' => 'yearly',
        ]);
    }

    private function obligation(Contract $contract, bool $recurring = true, int $months = 12, string $due = '2026-06-30'): ContractObligation {
        return app(ContractService::class)->addObligation($contract, [
            'kind' => ContractObligationKind::Documentation->value,
            'title' => 'Nachweis Spendenbescheinigung',
            'due_on' => $due,
            'warn_days_before' => 14,
            'recurring' => $recurring,
            'recurrence_months' => $recurring ? $months : null,
        ]);
    }

    /** Akzeptanzkriterium 3 aus Feature 201. */
    public function test_a_completed_recurring_obligation_shows_the_note_at_the_newly_created_obligation(): void {
        $contract = $this->contract();
        $obligation = $this->obligation($contract);
        $this->assertSame('contract_obligation:' . $contract->id . ':documentation', $obligation->carryForwardSeries());
        $this->assertSame('2026', (string) $obligation->carryForwardPeriod());

        $this->actingAs($this->admin)->post(route('carry-forward-notes.store'), [
            'subject_type' => $obligation->getMorphClass(),
            'subject_id' => (string) $obligation->getRouteKey(),
            'note' => 'Nächstes Jahr Spendenbescheinigung anfordern',
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(ContractService::class)->completeObligation($obligation, $this->admin);
        $next = $contract->obligations()->where('status', ContractObligationStatus::Open)->sole();
        $this->assertSame('2027-06-30', $next->due_on->toDateString());

        $open = $this->notes->forOccurrence($next)['open'];
        $this->assertSame(['Nächstes Jahr Spendenbescheinigung anfordern'], $open->pluck('note')->all());
        $this->assertTrue($this->notes->forOccurrence($obligation->refresh())['open']->isEmpty(), 'nicht an der eigenen Periode');

        $this->actingAs($this->admin)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('Nächstes Jahr Spendenbescheinigung anfordern')
            ->assertSee('carry-forward-' . $next->sqid, false);
    }

    public function test_only_recurring_obligations_form_a_series_and_the_interval_sets_the_period_kind(): void {
        $contract = $this->contract();
        $this->assertNull($this->obligation($contract, recurring: false)->carryForwardSeries());

        $quarterly = $this->obligation($contract, months: 3, due: '2026-05-31');
        $this->assertSame('2026-Q2', (string) $quarterly->carryForwardPeriod());
        $this->assertSame('2026-H2', (string) $this->obligation($contract, months: 6, due: '2026-09-30')->carryForwardPeriod());
        $this->assertSame('2026-08', (string) $this->obligation($contract, months: 1, due: '2026-08-01')->carryForwardPeriod());
        $this->assertSame(PeriodKind::Year, PeriodKind::forMonths(24));

        // Andere Pflichtart, anderer Vertrag: fremde Reihe.
        $note = $this->notes->create($quarterly, 'Zählerstand mitschicken', $this->admin);
        $other = $this->obligation($this->contract(), months: 3, due: '2026-08-31');
        $this->assertNotSame($quarterly->carryForwardSeries(), $other->carryForwardSeries());
        $this->assertFalse($this->notes->forOccurrence($other)['open']->contains('id', $note->id));
    }

    public function test_recurring_orders_of_monthly_and_yearly_rules_carry_notes_weekly_rules_do_not(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $rule = fn (RecurrenceFrequency $frequency, int $interval = 1): RecurrenceRule => RecurrenceRule::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'name' => 'Regel ' . $frequency->value . $interval,
            'content_template' => 'Wartung',
            'default_location_mode' => LocationMode::Onsite->value,
            'frequency' => $frequency->value,
            'interval' => $interval,
            'starts_on' => '2026-01-01',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $order = fn (RecurrenceRule $r, string $due): DiaryEntry => DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'recurrence_rule_id' => $r->id,
            'due_date' => $due,
        ]);

        $quarterly = $rule(RecurrenceFrequency::Monthly, 3);
        $april = $order($quarterly, '2026-04-15');
        $july = $order($quarterly, '2026-07-15');
        $this->assertSame('recurrence_rule:' . $quarterly->id, $april->carryForwardSeries());
        $this->assertSame('2026-Q2', (string) $april->carryForwardPeriod());

        $this->notes->create($april, 'Schlüssel beim Hausmeister holen', $this->admin);
        $this->assertSame(['Schlüssel beim Hausmeister holen'], $this->notes->forOccurrence($july)['open']->pluck('note')->all());

        $this->assertSame('2026', (string) $order($rule(RecurrenceFrequency::Yearly), '2026-03-01')->carryForwardPeriod());
        $weekly = $order($rule(RecurrenceFrequency::Weekly), '2026-06-16');
        $this->assertNull($weekly->carryForwardSeries());
        $this->assertNull($weekly->carryForwardPeriod());
    }

    public function test_inspection_schedules_and_order_items_of_one_duty_share_the_series(): void {
        $this->seed(AssetComplianceCatalogSeeder::class);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Prüfwaage', 'asset_no' => 'W-7']);
        $profile = AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'uvv_general')->firstOrFail();
        $assignment = app(AssetComplianceService::class)->assign($profile, $asset, $this->admin);
        $kind = PeriodKind::forMonths($assignment->intervalMonths());

        $schedule = fn (string $due): AssetInspectionSchedule => AssetInspectionSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
            'due_on' => $due,
            'status' => AssetInspectionScheduleStatus::Planned->value,
        ]);
        $thisYear = $schedule('2026-06-20');
        $nextDue = $kind === PeriodKind::Year ? '2027-06-20' : '2026-12-20';
        $nextTime = $schedule($nextDue);
        $this->assertSame('asset_inspection:' . $assignment->id, $thisYear->carryForwardSeries());

        $this->notes->create($thisYear, 'Kalibriergewicht 5 kg mitbringen', $this->admin);
        $this->assertSame(['Kalibriergewicht 5 kg mitbringen'], $this->notes->forOccurrence($nextTime)['open']->pluck('note')->all());

        // Position eines Prüfauftrags derselben Pflicht ist ebenfalls ein Vorkommen.
        $order = AssetInspectionOrder::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => Supplier::factory()->create(['organization_id' => $this->organization->id])->id,
            'title' => 'Prüfung extern',
            'recipient_email' => 'pruefdienst@example.test',
            'token_hash' => hash('sha256', 'merker-test'),
            'expires_at' => now()->addMonth(),
            'status' => 'requested',
            'created_by' => $this->admin->id,
        ]);
        $item = AssetInspectionOrderItem::query()->create([
            'organization_id' => $this->organization->id,
            'asset_inspection_order_id' => $order->id,
            'asset_inspection_schedule_id' => $nextTime->id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertSame($thisYear->carryForwardSeries(), $item->carryForwardSeries());
        $this->assertSame((string) $nextTime->carryForwardPeriod(), (string) $item->carryForwardPeriod());
        $this->assertSame(['Kalibriergewicht 5 kg mitbringen'], $this->notes->forOccurrence($item)['open']->pluck('note')->all());

        $this->actingAs($this->admin)->get(route('asset-compliance.orders.show', $order))->assertOk()->assertSee('Kalibriergewicht 5 kg mitbringen');
        $this->actingAs($this->admin)->get(route('asset-compliance.schedules.index'))->assertOk()->assertSee('Kalibriergewicht 5 kg mitbringen');

        // Cockpit des Assets zählt den offenen Merker.
        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()
            ->assertSee('cockpit-carry-forward-inspections', false)
            ->assertSee('Kalibriergewicht 5 kg mitbringen');
        $this->assertInstanceOf(AssetComplianceAssignment::class, $assignment);
    }

    public function test_the_customer_cockpit_counts_open_notes_of_contracts_and_recurring_orders_only_with_rights(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $obligation = $this->obligation($this->contract($customer));
        $this->notes->create($obligation, 'Preisanpassung ansprechen', $this->admin);
        $resolved = $this->notes->create($obligation, 'Bereits erledigt', $this->admin);
        $resolved->forceFill(['resolved_at' => now(), 'resolved_by' => $this->admin->id])->save();

        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $rule = RecurrenceRule::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'name' => 'Monatsabschluss',
            'content_template' => 'Monatsabschluss',
            'default_location_mode' => LocationMode::Remote->value,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'starts_on' => '2026-01-01',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $customer->id, 'recurrence_rule_id' => $rule->id, 'due_date' => '2026-06-30']);
        $this->notes->create($entry, 'Belege bis zum 5. anfordern', $this->admin);

        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee('cockpit-carry-forward-contracts', false)
            ->assertSee('Preisanpassung ansprechen')
            ->assertDontSee('Bereits erledigt')
            ->assertSee('cockpit-carry-forward-recurring-orders', false)
            ->assertSee('Belege bis zum 5. anfordern');

        $this->assertSame(2, CarryForwardNote::query()->whereNull('resolved_at')->count());
        $viewer = $this->orgUser();
        $this->actingAs($viewer)->get(route('customers.show', $customer))
            ->assertDontSee('Preisanpassung ansprechen')
            ->assertDontSee('Belege bis zum 5. anfordern');
    }

    /** Merker an Aufträgen mit Periode (Reihe `order:<kunde>:<art>:<produktgruppe>`) zählen im Kunden-Cockpit mit. */
    public function test_the_customer_cockpit_counts_open_notes_of_period_orders(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $taxReturn = EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'steuererklaerung', 'label' => 'Steuererklärung', 'requires_period' => [PeriodKind::Year->value]]);
        Classification::query()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::ProductGroup, 'code' => 'einkommensteuer', 'label' => 'Einkommensteuer', 'sort_order' => 10, 'active' => true]);
        $order = function (Customer $customer, string $period) use ($taxReturn): DiaryEntry {
            $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $customer->id, 'entry_type_id' => $taxReturn->id, 'period_key' => $period]);
            $entry->assignProductGroup('einkommensteuer');

            return $entry->refresh();
        };

        $income2025 = $order($customer, '2025');
        $this->notes->create($income2025, 'Spendenbescheinigung vorab anfordern', $this->admin);
        $done = $this->notes->create($income2025, 'Schon erledigt', $this->admin);
        $done->forceFill(['resolved_at' => now(), 'resolved_by' => $this->admin->id])->save();
        $income2026 = $order($customer, '2026');
        // Gleiche Art beim anderen Kunden: eigene Reihe, nicht in diesem Cockpit.
        $this->notes->create($order($other, '2025'), 'Fremder Merker', $this->admin);

        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee('cockpit-carry-forward-period-orders', false)
            ->assertSee('Spendenbescheinigung vorab anfordern')
            ->assertSee('Steuererklärung · Einkommensteuer')
            ->assertSee(route('diary.show', $income2026), false)
            ->assertDontSee('Schon erledigt')
            ->assertDontSee('Fremder Merker');

        // Ohne Einsicht in alle Aufträge kein Panel.
        $this->actingAs($this->orgUser())->get(route('customers.show', $customer))
            ->assertDontSee('cockpit-carry-forward-period-orders', false)
            ->assertDontSee('Spendenbescheinigung vorab anfordern');

        // Ohne offene Merker kein Panel.
        CarryForwardNote::query()->whereNull('resolved_at')->update(['resolved_at' => now(), 'resolved_by' => $this->admin->id]);
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()
            ->assertDontSee('cockpit-carry-forward-period-orders', false);
    }

    public function test_series_prefix_lookup_takes_wildcards_literally(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $entryType = EntryType::factory()->create(['organization_id' => $this->organization->id, 'requires_period' => [PeriodKind::Year->value]]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'customer_id' => $customer->id, 'entry_type_id' => $entryType->id, 'period_key' => '2025']);
        $this->notes->create($entry, 'Unterlagen anfordern', $this->admin);
        $series = (string) $entry->carryForwardSeries();

        $this->assertSame([$series], $this->notes->openSeriesStartingWith($this->organization->id, DiaryEntry::carryForwardOrderPrefix($customer->id)));
        $this->assertSame([], $this->notes->openSeriesStartingWith($this->organization->id, 'order:%'));
        $this->assertSame([], $this->notes->openSeriesStartingWith($this->organization->id, 'order_' . $customer->id));
        $this->assertSame([], $this->notes->openSeriesStartingWith(Organization::factory()->create()->id, DiaryEntry::carryForwardOrderPrefix($customer->id)));
    }
}
