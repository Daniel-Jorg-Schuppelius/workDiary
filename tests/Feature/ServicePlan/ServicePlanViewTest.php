<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\ServicePlan;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Enums\User\UserRole;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\ServicePlan\CustomerServiceWaiver;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets};
use App\Services\Customer\Deadlines\RuleSets\TaxAdvisoryDeadlineRuleSet;
use App\Services\Delegation\WorkDelegationService;
use App\Services\ServicePlan\Dto\{ServiceKey, ServicePlanResult};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Leistungsplan, Sichten (Feature 199, MVP-1638/1639) mit den
 * Akzeptanzkriterien 1–4, Filtern, Export, Rechten und dem Anschluss der
 * Vertretung (MVP-1648). Alle Kunden und Personen sind erfunden.
 */
final class ServicePlanViewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $anna;

    private User $ben;

    private EntryType $closing;

    private EntryType $support;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-05-15 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->admin = $this->orgAdmin();
        $this->anna = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Anna Beispiel']);
        $this->ben = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Ben Muster']);
        $this->closing = $this->entryType('abschluss', 'Jahresabschluss', PeriodKind::Year);
        $this->support = $this->entryType('maintenance', 'Monatsbetreuung', PeriodKind::Month);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function entryType(string $slug, string $label, PeriodKind $kind): EntryType {
        return EntryType::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => $slug,
            'label' => $label,
            'requires_customer' => true,
            'requires_period' => [$kind->value],
        ]);
    }

    private function customer(string $name, string $number): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name, 'number' => $number]);
    }

    private function service(Customer $customer, EntryType $type, PeriodKind $kind, string $from = '2025-01-01', bool $mandatory = true): CustomerService {
        return CustomerService::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'entry_type_id' => $type->id,
            'period_kind' => $kind,
            'is_mandatory' => $mandatory,
            'valid_from' => $from,
        ]);
    }

    private function order(Customer $customer, EntryType $type, string $period, Status $status, ?User $assignee = null): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'assigned_user_id' => $assignee?->id,
            'customer_id' => $customer->id,
            'entry_type_id' => $type->id,
            'period_key' => $period,
            'status' => $status->value,
            'title' => $type->label . ' ' . $period,
        ]);
    }

    /** @param  array<string, mixed>  $query */
    private function plan(User $viewer, string $route, array $query = []): ServicePlanResult {
        $result = null;
        $this->actingAs($viewer)->get(route($route, $query))
            ->assertOk()
            ->assertViewHas('result', static function (ServicePlanResult $value) use (&$result): bool {
                $result = $value;

                return true;
            });
        $this->assertInstanceOf(ServicePlanResult::class, $result);

        return $result;
    }

    /** Abnahme 1 und 2: Auftrag zur Prüfung erscheint so; „fehlt“ zählt, bis „entfällt“ markiert ist. */
    public function test_year_view_reads_status_from_orders_and_counts_missing_until_waived(): void {
        $bakery = $this->customer('Bäckerei Probe', 'K-100');
        $office = $this->customer('Büro Beispiel', 'K-200');
        $this->service($bakery, $this->closing, PeriodKind::Year);
        $this->service($office, $this->closing, PeriodKind::Year);
        $this->order($bakery, $this->closing, '2025', Status::InReview, $this->anna);

        $result = $this->plan($this->anna, 'service-plan.index', ['year' => '2025']);
        $key = (new ServiceKey((int) $this->closing->id))->key();
        $rows = [];
        foreach ($result->rows as $row) {
            $rows[$row->customerId] = $row;
        }
        $this->assertSame(ServicePlanCellState::Review, $rows[$bakery->id]->cells[$key]->state);
        $this->assertSame(ServicePlanCellState::Missing, $rows[$office->id]->cells[$key]->state);
        $this->assertSame(1, $result->counts['review']);
        $this->assertSame(1, $result->counts['missing']);
        $this->actingAs($this->anna)->get(route('service-plan.index', ['year' => '2025']))
            ->assertSee(ServicePlanCellState::Review->label())
            ->assertSee('Bäckerei Probe');

        $params = ['customer' => $office->sqid, 'service' => (new ServiceKey((int) $this->closing->id))->param(), 'period' => '2025'];
        $this->actingAs($this->anna)->post(route('service-plan.cell.update', $params), ['action' => 'waive', 'reason' => 'Kunde hat gekündigt'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $after = $this->plan($this->anna, 'service-plan.index', ['year' => '2025']);
        $this->assertSame(0, $after->counts['missing']);
        $this->assertSame(1, $after->counts['waived']);
        $this->assertDatabaseHas('customer_service_waivers', ['customer_id' => $office->id, 'period_key' => '2025', 'reason' => 'Kunde hat gekündigt', 'created_by' => $this->anna->id]);
    }

    /** Abnahme 3: Monatsbetreuung zeigt zwölf Zellen, drei erledigte Monatsaufträge als erledigt. */
    public function test_period_view_shows_twelve_months_with_done_orders(): void {
        $client = $this->customer('Kanzlei Musterweg', 'K-300');
        $this->service($client, $this->support, PeriodKind::Month, '2026-01-01');
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            $this->order($client, $this->support, $month, Status::Done);
        }

        $result = $this->plan($this->anna, 'service-plan.periods.index', ['year' => '2026']);
        $this->assertCount(1, $result->rows);
        $cells = $result->rows[0]->cells;
        $this->assertCount(12, $cells);
        $this->assertSame(ServicePlanCellState::Done, $cells['2026-01']->state);
        $this->assertSame(ServicePlanCellState::Done, $cells['2026-03']->state);
        $this->assertSame(ServicePlanCellState::Missing, $cells['2026-04']->state);
        $this->assertTrue($cells['2026-04']->overdue, 'April ist vorbei — ohne Auftrag überfällig');
        $this->assertFalse($cells['2026-06']->overdue);
        $this->assertSame(3, $result->counts['done']);
        $this->assertSame(9, $result->counts['missing']);
    }

    /** Abnahme 4: vierteljährliche USt — die Abgabefrist steht nur in den Quartalsmonaten. */
    public function test_quarterly_vat_deadline_shows_only_in_quarter_months(): void {
        $advance = $this->entryType(TaxAdvisoryDeadlineRuleSet::ENTRY_ADVANCE, 'Voranmeldung', PeriodKind::Month);
        $client = $this->customer('Planungsbüro Probe', 'K-400');
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
        $ruleSet = app(CustomerDeadlineRuleSets::class)->get($this->organization, TaxAdvisoryDeadlineRuleSet::KEY);
        $this->assertNotNull($ruleSet);
        app(CustomerDeadlineProfileService::class)->save($client, $ruleSet, ['vat_interval' => 'quarterly', 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin'], CarbonImmutable::parse('2026-01-01'), $this->anna, $this->admin);
        $this->service($client, $advance, PeriodKind::Month, '2026-01-01');

        $result = $this->plan($this->anna, 'service-plan.periods.index', ['year' => '2026', 'rule_set' => TaxAdvisoryDeadlineRuleSet::KEY]);
        $cells = $result->rows[0]->cells;
        $withDeadline = array_keys(array_filter($cells, static fn ($cell): bool => $cell->deadlineId !== null));
        $this->assertSame(['2026-03', '2026-06', '2026-09', '2026-12'], $withDeadline);
        $this->assertSame('2026-04-10', $cells['2026-03']->due?->toDateString());
        // Zusatzspalten aus den Merkmalen des Regelsatzes (Feldschema aus Feature 197).
        $this->assertContains('vat_interval', array_map(static fn ($field): string => $field->key, $result->traitFields));
        $this->assertSame('quarterly', $result->traitsOf((int) $client->id)?->get('vat_interval'));
        // Zuständig ohne Auftrag: die Person der Fristmerkmale.
        $this->assertSame((int) $this->anna->id, $cells['2026-01']->responsibleId);
    }

    /** Filter: Zuständigkeit „Meine“ schließt die Vertretung ein (MVP-1648), Stand und Suche filtern Zeilen. */
    public function test_filters_include_cells_of_represented_people(): void {
        $first = $this->customer('Erste Probe GmbH', 'K-501');
        $second = $this->customer('Zweite Probe GmbH', 'K-502');
        $this->service($first, $this->closing, PeriodKind::Year);
        $this->service($second, $this->closing, PeriodKind::Year);
        $this->order($first, $this->closing, '2025', Status::InProgress, $this->anna);
        $this->order($second, $this->closing, '2025', Status::Done, $this->ben);

        $mine = $this->plan($this->ben, 'service-plan.index', ['year' => '2025', 'responsible' => 'mine']);
        $this->assertSame([(int) $second->id], array_map(static fn ($row): int => $row->customerId, $mine->rows));

        app(WorkDelegationService::class)->create($this->anna, $this->ben, CarbonImmutable::today(), null, WorkDelegationKind::Vacation, null, null, $this->anna);
        $deputy = $this->plan($this->ben, 'service-plan.index', ['year' => '2025', 'responsible' => 'mine']);
        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $second->id], array_map(static fn ($row): int => $row->customerId, $deputy->rows));

        $open = $this->plan($this->ben, 'service-plan.index', ['year' => '2025', 'state' => 'open']);
        $this->assertSame([(int) $first->id], array_map(static fn ($row): int => $row->customerId, $open->rows));
        $search = $this->plan($this->ben, 'service-plan.index', ['year' => '2025', 'q' => 'zweite']);
        $this->assertSame([(int) $second->id], array_map(static fn ($row): int => $row->customerId, $search->rows));
    }

    /** Ohne Leserecht des Plans: nur eigene Zellen, die anderen verdeckt; ohne Zugang zum Auftragsbuch nichts. */
    public function test_people_without_plan_permission_see_only_their_cells(): void {
        $worker = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Wera Probe']);
        $own = $this->customer('Eigene Probe', 'K-601');
        $foreign = $this->customer('Fremde Probe', 'K-602');
        $this->service($own, $this->closing, PeriodKind::Year);
        $this->service($foreign, $this->closing, PeriodKind::Year);
        $this->order($own, $this->closing, '2025', Status::InProgress, $worker);

        $result = $this->plan($worker, 'service-plan.index', ['year' => '2025']);
        $this->assertSame([(int) $own->id], array_map(static fn ($row): int => $row->customerId, $result->rows));
        $this->assertSame(0, $result->counts['missing']);

        $params = ['customer' => $foreign->sqid, 'service' => (new ServiceKey((int) $this->closing->id))->param(), 'period' => '2025'];
        $this->actingAs($worker)->get(route('service-plan.cell.edit', $params))->assertForbidden();
        $this->actingAs($worker)->post(route('service-plan.cell.update', $params), ['action' => 'waive', 'reason' => 'x'])->assertForbidden();

        $stranger = $this->userWithRole(UserRole::Teamleitung->value, Organization::factory()->create());
        $this->actingAs($stranger)->get(route('service-plan.cell.edit', $params))->assertNotFound();
    }

    public function test_export_as_csv_and_xlsx(): void {
        $client = $this->customer('Export Probe', 'K-700');
        $this->service($client, $this->closing, PeriodKind::Year);
        $this->service($client, $this->support, PeriodKind::Month, '2026-01-01');
        $this->order($client, $this->closing, '2025', Status::Done);

        $csv = $this->actingAs($this->anna)->get(route('service-plan.index', ['year' => '2025', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('Export Probe', (string) $csv->getContent());
        $this->assertStringContainsString(ServicePlanCellState::Done->label(), (string) $csv->getContent());
        $this->actingAs($this->anna)->get(route('service-plan.index', ['year' => '2025', 'export' => 'xlsx']))->assertOk();
        $periods = $this->actingAs($this->anna)->get(route('service-plan.periods.index', ['year' => '2026', 'export' => 'csv']));
        $periods->assertOk();
        $this->assertStringContainsString('Monatsbetreuung', (string) $periods->getContent());
    }

    public function test_cell_dialog_and_prefilled_order(): void {
        $client = $this->customer('Dialog Probe', 'K-800');
        $this->service($client, $this->support, PeriodKind::Month, '2026-01-01');
        $params = ['customer' => $client->sqid, 'service' => (new ServiceKey((int) $this->support->id))->param(), 'period' => '2026-05'];

        $this->actingAs($this->anna)->get(route('service-plan.cell.edit', $params + ['dialog' => 1]))
            ->assertOk()
            ->assertSee('Monatsbetreuung')
            ->assertSee(__('service_plan.dialog.create_order'));

        $this->actingAs($this->anna)->get(route('service-plan.cell.order', $params))
            ->assertRedirect()
            ->assertSessionHasInput('period_key', '2026-05')
            ->assertSessionHasInput('due_date', '2026-05-31');

        $order = $this->order($client, $this->support, '2026-05', Status::Accepted, $this->anna);
        $this->actingAs($this->anna)->get(route('service-plan.cell.order', $params))->assertRedirect(route('diary.show', $order));
        $this->actingAs($this->anna)->post(route('service-plan.cell.update', $params), ['action' => 'waive', 'reason' => 'zu spät'])->assertSessionHasErrors('period');
        $this->assertSame(0, CustomerServiceWaiver::query()->count());
    }

    /** Kunden-Cockpit: Panel „Leistungsplan“ mit den Zellen bis heute, offene zuerst. */
    public function test_customer_cockpit_shows_the_plan_panel(): void {
        $client = $this->customer('Cockpit Probe', 'K-950');
        $this->service($client, $this->support, PeriodKind::Month, '2026-01-01');
        $this->order($client, $this->support, '2026-01', Status::Done);

        $this->actingAs($this->anna)->get(route('customers.show', $client))
            ->assertOk()
            ->assertSee('id="service-plan"', false)
            ->assertSee('Monatsbetreuung · ' . \App\Support\Period\PeriodKey::parse('2026-05')->label(), false);

        $empty = $this->customer('Ohne Leistungen', 'K-951');
        $this->actingAs($this->anna)->get(route('customers.show', $empty))->assertOk()->assertDontSee('id="service-plan"', false);
    }

    /** Unterlagen und Bemerkung: die zuständige Person darf es ohne Pflegerecht, andere nicht. */
    public function test_documents_and_note_by_responsible_person(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $other = $this->userWithRole(UserRole::User->value);
        $client = $this->customer('Unterlagen Probe', 'K-900');
        $this->service($client, $this->closing, PeriodKind::Year);
        $this->order($client, $this->closing, '2025', Status::InProgress, $worker);
        $params = ['customer' => $client->sqid, 'service' => (new ServiceKey((int) $this->closing->id))->param(), 'period' => '2025'];

        $this->actingAs($worker)->post(route('service-plan.cell.update', $params), ['action' => 'note', 'documents_received_on' => '2026-03-01', 'note' => 'Belege vollständig'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_service_period_notes', ['customer_id' => $client->id, 'period_key' => '2025', 'note' => 'Belege vollständig']);
        $this->actingAs($other)->post(route('service-plan.cell.update', $params), ['action' => 'note', 'note' => 'nein'])->assertForbidden();

        $result = $this->plan($this->anna, 'service-plan.index', ['year' => '2025']);
        $cell = $result->rows[0]->cells[(new ServiceKey((int) $this->closing->id))->key()];
        $this->assertSame('2026-03-01', $cell->documentsReceivedOn?->toDateString());
    }
}
