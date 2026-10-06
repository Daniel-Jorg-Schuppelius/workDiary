<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IndexListPaginationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use App\Enums\Shift\ShiftPreference;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission;
use App\Models\Crisis\CrisisBusinessProcess;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProviderConnection, DomainResellerAccount};
use App\Models\Finance\CashRegister;
use App\Models\Inventory\{Warehouse, WarehouseBin};
use App\Models\Investments\{InvestmentProgram, StrategicObjective};
use App\Models\Manufacturing\WorkCenter;
use App\Models\Platform\{User, UserBookmark, UserFilterPreset, UserWorkspace};
use App\Models\Privacy\ComplianceFinding;
use App\Models\Reporting\SavedReportView;
use App\Models\Safety\HazardCatalogItem;
use App\Models\Sales\CommissionAgent;
use App\Models\Schedule\{CoverageRequirement, DesiredShift, DutyPlan, ScheduledShift, ShiftExchange, ShiftType};
use App\Models\ServiceTicket\SlaContract;
use App\Models\Time\{FlexEligibility, MonthClosure};
use App\Services\Navigation\NavigationRegistry;
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k4-14 (letztes Paket): die Listen blättern,
 * und was sortiert oder zählt, gilt über alle Seiten.
 */
class IndexListPaginationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const FILL = 'class="wd-surface min-h-0 flex flex-col lg:overflow-clip"';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_investment_objectives_and_programs_page(): void {
        foreach (range(1, 26) as $i) {
            StrategicObjective::query()->create(['organization_id' => $this->organization->id, 'title' => sprintf('Ziel %02d', $i), 'is_active' => $i !== 1]);
            InvestmentProgram::query()->create(['organization_id' => $this->organization->id, 'name' => sprintf('Programm %02d', $i), 'starts_year' => 2026, 'ends_year' => 2027, 'currency' => 'EUR', 'status' => 'planning']);
        }

        $first = $this->actingAs($this->admin)->get(route('investments.objectives.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'objectives', 26, 25);
        // Das inaktive Ziel steht hinter allen aktiven — auf der zweiten Seite.
        $second = $this->get(route('investments.objectives.index', ['page' => 2]))->assertOk();
        $this->assertSame(['Ziel 01'], $this->column($second, 'objectives', 'title'));

        $this->assertPage($this->get(route('investments.programs.index'))->assertOk()->assertSee(self::FILL, false), 'programs', 26, 25);
        $this->assertSame(['Programm 26'], $this->column($this->get(route('investments.programs.index', ['page' => 2]))->assertOk(), 'programs', 'name'));
    }

    public function test_bia_register_pages_by_criticality(): void {
        foreach (range(1, 26) as $i) {
            CrisisBusinessProcess::query()->create(['organization_id' => $this->organization->id, 'name' => sprintf('Prozess %02d', $i), 'criticality' => $i === 26 ? 'critical' : 'low']);
        }

        $first = $this->actingAs($this->admin)->get(route('crisis.bia.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'processes', 26, 25);
        $this->assertSame('Prozess 26', $this->column($first, 'processes', 'name')[0]);
        $this->assertSame(['Prozess 25'], $this->column($this->get(route('crisis.bia.index', ['page' => 2]))->assertOk(), 'processes', 'name'));
    }

    public function test_domain_reseller_accounts_page(): void {
        $this->admin->givePermissionTo(Permission::DomainViewAny->value);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        foreach (range(1, 26) as $i) {
            DomainResellerAccount::factory()->create([
                'organization_id' => $this->organization->id,
                'connection_id' => $connection->id,
                'external_user' => sprintf('konto%02d', $i),
                'depth' => $i === 26 ? 0 : 1,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('domain-reseller.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'accounts', 26, 25);
        $this->assertSame('konto26', $this->column($first, 'accounts', 'external_user')[0]);
        $this->assertSame(['konto25'], $this->column($this->get(route('domain-reseller.index', ['page' => 2]))->assertOk(), 'accounts', 'external_user'));
    }

    public function test_work_centers_page_and_load_is_computed_for_the_page(): void {
        foreach (range(1, 26) as $i) {
            WorkCenter::query()->create(['organization_id' => $this->organization->id, 'name' => sprintf('Platz %02d', $i), 'capacity_minutes' => 480]);
        }

        $first = $this->actingAs($this->admin)->get(route('work-centers.index'))->assertOk();
        $this->assertPage($first, 'board', 26, 25);
        $row = $first->viewData('board')->items()[0];
        $this->assertSame('Platz 01', $row['center']->name);
        $this->assertArrayHasKey('utilization', $row['load']);

        $second = $this->get(route('work-centers.index', ['page' => 2]))->assertOk()->assertSee('Platz 26');
        $this->assertPage($second, 'board', 26, 1);
    }

    public function test_cash_registers_page_with_their_balances(): void {
        foreach (range(1, 26) as $i) {
            CashRegister::create(['organization_id' => $this->organization->id, 'name' => sprintf('Kasse %02d', $i), 'currency' => 'EUR', 'opening_balance' => (string) $i, 'opened_on' => '2030-01-01']);
        }

        $first = $this->actingAs($this->admin)->get(route('cash-registers.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'registers', 26, 25);
        $this->assertCount(25, $first->viewData('balances'));

        $second = $this->get(route('cash-registers.index', ['page' => 2]))->assertOk()->assertSee('Kasse 26');
        $this->assertSame(26.0, (float) array_values($second->viewData('balances'))[0]);
    }

    public function test_saved_report_views_page_below_the_form(): void {
        $this->actingAs($this->admin)->get(route('report-views.index'))->assertOk()
            ->assertSee(self::FILL, false)->assertSee('Noch keine gespeicherten Ansichten');

        foreach (range(1, 26) as $i) {
            SavedReportView::query()->create([
                'organization_id' => $this->organization->id,
                'created_by' => $this->admin->id,
                'name' => sprintf('Ansicht %02d', $i),
                'route_name' => 'reports.absences',
                'params' => [],
                'is_shared' => false,
            ]);
        }

        $this->assertPage($this->get(route('report-views.index'))->assertOk()->assertSee('Neue Ansicht speichern'), 'views', 26, 25);
        $this->assertSame(['Ansicht 26'], $this->column($this->get(route('report-views.index', ['page' => 2]))->assertOk(), 'views', 'name'));
    }

    public function test_bookmarks_page_and_sort_on_the_server(): void {
        foreach (range(1, 26) as $i) {
            UserBookmark::create(['user_id' => $this->admin->id, 'label' => sprintf('Lesezeichen %02d', $i), 'url' => '/ziel-' . (100 - $i), 'sort_order' => $i]);
        }
        UserBookmark::create(['user_id' => $this->orgUser()->id, 'label' => 'Fremd', 'url' => '/fremd', 'sort_order' => 0]);

        $first = $this->actingAs($this->admin)->get(route('bookmarks.index'))->assertOk();
        $this->assertPage($first, 'bookmarks', 26, 25);
        $this->assertSame('Lesezeichen 01', $this->column($first, 'bookmarks', 'label')[0]);
        $this->assertSame(['Lesezeichen 26'], $this->column($this->get(route('bookmarks.index', ['page' => 2]))->assertOk(), 'bookmarks', 'label'));

        $byUrl = $this->get(route('bookmarks.index', ['sort' => 'url', 'dir' => 'asc', 'q' => 'Lesezeichen']))->assertOk();
        $this->assertSame('Lesezeichen 26', $this->column($byUrl, 'bookmarks', 'label')[0]);
        $this->assertStringContainsString('q=Lesezeichen', (string) $byUrl->viewData('bookmarks')->nextPageUrl());
        $this->assertStringContainsString('sort=url', (string) $byUrl->viewData('bookmarks')->nextPageUrl());
    }

    /** Bisher griff der Bereich nie: die Beziehung sortierte schon nach sort_order, id. */
    public function test_filter_presets_page_grouped_by_scope(): void {
        foreach (range(1, 26) as $i) {
            UserFilterPreset::create(['user_id' => $this->admin->id, 'scope' => $i === 26 ? 'auftraege' : 'zeiten', 'name' => sprintf('Filter %02d', $i), 'query' => [], 'sort_order' => $i]);
        }

        $first = $this->actingAs($this->admin)->get(route('filter-presets.index'))->assertOk();
        $this->assertPage($first, 'presets', 26, 25);
        // Nach sort_order stünde Filter 26 ganz hinten; sein Bereich zieht ihn nach vorn.
        $this->assertSame('Filter 26', $this->column($first, 'presets', 'name')[0]);
        $this->assertSame('Filter 01', $this->column($first, 'presets', 'name')[1]);

        $byName = $this->get(route('filter-presets.index', ['sort' => 'name', 'dir' => 'desc', 'page' => 2]))->assertOk();
        $this->assertSame(['Filter 01'], $this->column($byName, 'presets', 'name'));
    }

    public function test_workspaces_page_and_sort_on_the_server(): void {
        $keys = array_slice(app(NavigationRegistry::class)->selectableKeys(), 0, 2);
        foreach (range(1, 26) as $i) {
            UserWorkspace::create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'name' => sprintf('Bereich %02d', $i), 'sort' => 30 - $i, 'items' => $keys]);
        }

        $first = $this->actingAs($this->admin)->get(route('me.workspaces.index'))->assertOk();
        $this->assertPage($first, 'workspaces', 26, 25);
        $this->assertSame('Bereich 26', $this->column($first, 'workspaces', 'name')[0]);

        $byName = $this->get(route('me.workspaces.index', ['sort' => 'name', 'dir' => 'asc', 'page' => 2]))->assertOk();
        $this->assertSame(['Bereich 26'], $this->column($byName, 'workspaces', 'name'));
    }

    public function test_commission_agents_page_and_sort_on_the_server(): void {
        foreach (range(1, 51) as $i) {
            CommissionAgent::query()->create(['organization_id' => $this->organization->id, 'name' => sprintf('Agent %02d', $i), 'company' => 'Firma ' . (100 - $i), 'is_active' => $i !== 1]);
        }

        $first = $this->actingAs($this->admin)->get(route('commission-agents.index'))->assertOk();
        $this->assertPage($first, 'agents', 51, 50);
        $this->assertSame('Agent 01', $this->column($first, 'agents', 'name')[0]);

        $byCompany = $this->get(route('commission-agents.index', ['sort' => 'company', 'dir' => 'asc']))->assertOk();
        $this->assertSame('Agent 51', $this->column($byCompany, 'agents', 'name')[0]);
        $inactiveFirst = $this->get(route('commission-agents.index', ['sort' => 'is_active', 'dir' => 'asc']))->assertOk();
        $this->assertSame('Agent 01', $this->column($inactiveFirst, 'agents', 'name')[0]);
    }

    public function test_shift_types_page_and_sort_by_usage_on_the_server(): void {
        $types = [];
        foreach (range(1, 26) as $i) {
            $types[$i] = ShiftType::factory()->create(['organization_id' => $this->organization->id, 'name' => sprintf('Schicht %02d', $i), 'abbreviation' => sprintf('S%02d', $i)]);
        }
        ScheduledShift::factory()->count(2)->create(['organization_id' => $this->organization->id, 'shift_type_id' => $types[26]->id]);

        $first = $this->actingAs($this->admin)->get(route('shift-types.index'))->assertOk();
        $this->assertPage($first, 'types', 26, 25);
        $this->assertSame('Schicht 01', $this->column($first, 'types', 'name')[0]);

        // Die meistverwendete Schicht stand bisher nur nach Klick innerhalb der Seite oben.
        $byUsage = $this->get(route('shift-types.index', ['sort' => 'used', 'dir' => 'desc']))->assertOk();
        $this->assertSame('Schicht 26', $this->column($byUsage, 'types', 'name')[0]);
        $this->assertSame(2, (int) $byUsage->viewData('types')->items()[0]->scheduled_shifts_count);
    }

    /** Bisher zeigte die Seite still nur die 24 jüngsten Monate. */
    public function test_month_closures_page_beyond_two_years(): void {
        foreach (range(0, 25) as $i) {
            $period = now()->startOfMonth()->subMonths($i);
            MonthClosure::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->admin->id,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'status' => MonthClosureStatus::Draft->value,
                'days_open' => $i,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('month-approval.index'))->assertOk();
        $this->assertPage($first, 'closures', 26, 24);
        $this->assertSame(0, $first->viewData('closures')->items()[0]->days_open);

        $second = $this->get(route('month-approval.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'closures', 26, 2);
        $this->assertSame([24, 25], $this->column($second, 'closures', 'days_open'));

        $byOpenDays = $this->get(route('month-approval.index', ['sort' => 'days_open', 'dir' => 'desc']))->assertOk();
        $this->assertSame(25, $byOpenDays->viewData('closures')->items()[0]->days_open);
    }

    public function test_warehouse_bins_page_and_sort_on_the_server(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        foreach (range(1, 51) as $i) {
            WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $warehouse->id, 'code' => sprintf('P-%02d', $i), 'name' => 'Fach ' . (100 - $i), 'sort_order' => $i]);
        }

        $first = $this->actingAs($this->admin)->get(route('warehouses.bins.index', $warehouse))->assertOk();
        $this->assertPage($first, 'bins', 51, 50);
        $this->assertSame('P-01', $this->column($first, 'bins', 'code')[0]);
        $this->assertSame(['P-51'], $this->column($this->get(route('warehouses.bins.index', [$warehouse, 'page' => 2]))->assertOk(), 'bins', 'code'));

        $byName = $this->get(route('warehouses.bins.index', [$warehouse, 'sort' => 'name', 'dir' => 'asc']))->assertOk();
        $this->assertSame('P-51', $this->column($byName, 'bins', 'code')[0]);
        $this->get(route('warehouses.bins.index', [$warehouse, 'sort' => 'movements', 'dir' => 'desc']))->assertOk();
    }

    public function test_flex_eligibilities_page_and_open_periods_sort_as_unbounded(): void {
        $member = $this->orgUser();
        foreach (range(1, 26) as $i) {
            FlexEligibility::create([
                'organization_id' => $this->organization->id,
                'user_id' => $member->id,
                'valid_from' => sprintf('20%02d-01-01', $i),
                'valid_to' => $i === 26 ? null : sprintf('20%02d-12-31', $i),
                'note' => 'Periode ' . $i,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('users.flex-eligibility.index', $member))->assertOk();
        $this->assertPage($first, 'periods', 26, 25);
        $this->assertSame('Periode 26', $this->column($first, 'periods', 'note')[0]);
        $this->assertSame(['Periode 1'], $this->column($this->get(route('users.flex-eligibility.index', [$member, 'page' => 2]))->assertOk(), 'periods', 'note'));

        // Die offene Periode steht hinter jedem Enddatum — auch über die Seitengrenze.
        $byEnd = $this->get(route('users.flex-eligibility.index', [$member, 'sort' => 'valid_to', 'dir' => 'asc', 'page' => 2]))->assertOk();
        $this->assertSame(['Periode 26'], $this->column($byEnd, 'periods', 'note'));
    }

    public function test_hazard_catalog_pages_and_sorts_by_risk_on_the_server(): void {
        foreach (range(1, 31) as $i) {
            HazardCatalogItem::query()->create([
                'organization_id' => $this->organization->id,
                'code' => 'own/' . $i,
                'category' => 'Kategorie ' . chr(64 + (($i - 1) % 26) + 1),
                'hazard' => sprintf('Gefährdung %02d', $i),
                'severity' => $i === 31 ? 5 : 1,
                'likelihood' => $i === 31 ? 5 : 1,
                'is_active' => $i !== 1,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('safety.hazard-catalog.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'items', 31, 30);
        // Standard wie bisher: der inaktive Eintrag steht hinter allen aktiven.
        $this->assertSame(['Gefährdung 01'], $this->column($this->get(route('safety.hazard-catalog.index', ['page' => 2]))->assertOk(), 'items', 'hazard'));

        $byRisk = $this->get(route('safety.hazard-catalog.index', ['sort' => 'risk', 'dir' => 'desc']))->assertOk();
        $this->assertSame('Gefährdung 31', $this->column($byRisk, 'items', 'hazard')[0]);
    }

    public function test_sla_contracts_page_with_the_default_first_and_sort_by_customer(): void {
        $this->admin->givePermissionTo(Permission::SlaContractView->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Aachener Werke']);
        foreach (range(1, 26) as $i) {
            SlaContract::factory()->create([
                'organization_id' => $this->organization->id,
                'code' => sprintf('SLA-%02d', $i),
                'is_active' => true,
                'is_default' => $i === 26,
                'customer_id' => $i === 25 ? $customer->id : null,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('sla-contracts.index'))->assertOk()->assertSee(self::FILL, false);
        $this->assertPage($first, 'contracts', 26, 25);
        $this->assertSame('SLA-26', $this->column($first, 'contracts', 'code')[0]);
        $this->assertSame('SLA-01', $this->column($first, 'contracts', 'code')[1]);

        $byCustomer = $this->get(route('sla-contracts.index', ['sort' => 'customer', 'dir' => 'desc']))->assertOk();
        $this->assertSame('SLA-25', $this->column($byCustomer, 'contracts', 'code')[0]);
        $this->get(route('sla-contracts.index', ['sort' => 'quotas', 'dir' => 'desc']))->assertOk();
        $this->get(route('sla-contracts.index', ['sort' => 'project']))->assertOk();
    }

    public function test_coverage_requirements_page_and_sort_by_shift_type_on_the_server(): void {
        $plan = DutyPlan::factory()->draft()->weekly()->create(['organization_id' => $this->organization->id, 'from_date' => '2026-05-18', 'to_date' => '2026-05-24']);
        $early = ShiftType::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Früh']);
        $late = ShiftType::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Spät']);
        foreach (range(1, 25) as $i) {
            CoverageRequirement::factory()->forDate(sprintf('2026-05-%02d', 1 + ($i % 28)))->create([
                'organization_id' => $this->organization->id,
                'duty_plan_id' => $plan->id,
                'shift_type_id' => $early->id,
                'min_staff' => $i,
            ]);
        }
        CoverageRequirement::factory()->forWeekday(1)->create(['organization_id' => $this->organization->id, 'duty_plan_id' => $plan->id, 'shift_type_id' => $late->id, 'min_staff' => 99]);

        $first = $this->actingAs($this->admin)->get(route('duty-plans.coverage.index', $plan))->assertOk();
        $this->assertPage($first, 'requirements', 26, 25);
        // Standard wie bisher: konkrete Daten vor der Wochentagsregel.
        $this->assertSame([99], $this->column($this->get(route('duty-plans.coverage.index', [$plan, 'page' => 2]))->assertOk(), 'requirements', 'min_staff'));

        $byShift = $this->get(route('duty-plans.coverage.index', [$plan, 'sort' => 'shift', 'dir' => 'desc']))->assertOk();
        $this->assertSame(99, $this->column($byShift, 'requirements', 'min_staff')[0]);
        $byMin = $this->get(route('duty-plans.coverage.index', [$plan, 'sort' => 'min', 'dir' => 'desc', 'page' => 2]))->assertOk();
        $this->assertSame([1], $this->column($byMin, 'requirements', 'min_staff'));
    }

    public function test_compliance_findings_page_while_the_status_tiles_count_all(): void {
        DataProtectionPermissions::seedOrganization($this->organization);
        $officer = $this->orgUser();
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        foreach (range(1, 21) as $i) {
            ComplianceFinding::create([
                'organization_id' => $this->organization->id,
                'requirement_key' => sprintf('regel_%02d', $i),
                'label' => sprintf('Anforderung %02d', $i),
                'status' => $i === 21 ? 'missing' : 'present',
            ]);
        }

        $first = $this->actingAs($officer)->get(route('dataprotection.compliance.index'))->assertOk();
        $this->assertPage($first, 'findings', 21, 20);
        // Die offene Lücke steht vorn, obwohl ihr Schlüssel der letzte ist.
        $this->assertSame('Anforderung 21', $this->column($first, 'findings', 'label')[0]);
        $this->assertSame(['missing' => 1, 'present' => 20], array_map(intval(...), $first->viewData('counts')->all()));

        $second = $this->get(route('dataprotection.compliance.index', ['page' => 2]))->assertOk();
        $this->assertSame(['Anforderung 20'], $this->column($second, 'findings', 'label'));
        // Auf der zweiten Seite steht kein offener Befund — die Ampel zeigt ihn trotzdem.
        $this->assertSame(1, (int) $second->viewData('counts')['missing']);
    }

    public function test_desired_shifts_page_newest_first_while_windows_stay_complete(): void {
        $user = $this->orgUser();
        foreach (range(1, 26) as $i) {
            DesiredShift::factory()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => now()->startOfDay()->addDays($i)->toDateString(),
                'preference' => ShiftPreference::Want,
                'note' => 'Wunsch ' . $i,
            ]);
        }

        $first = $this->actingAs($user)->get(route('schedule.availability.index'))->assertOk();
        $this->assertPage($first, 'desired', 26, 25);
        $this->assertSame('Wunsch 26', $this->column($first, 'desired', 'note')[0]);
        $this->assertSame(['Wunsch 1'], $this->column($this->get(route('schedule.availability.index', ['page' => 2]))->assertOk(), 'desired', 'note'));
    }

    public function test_own_shift_exchanges_page_while_the_approval_list_stays_complete(): void {
        $lead = $this->orgUser();
        $lead->givePermissionTo(Permission::ShiftExchangeApprove->value);
        $shift = ScheduledShift::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $lead->id]);
        ShiftExchange::factory()->count(26)->create([
            'organization_id' => $this->organization->id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $lead->id,
        ]);

        $first = $this->actingAs($lead)->get(route('schedule.exchanges.index'))->assertOk();
        $this->assertPage($first, 'mine', 26, 25);
        $this->assertCount(26, $first->viewData('pendingApproval'));
        $this->assertPage($this->get(route('schedule.exchanges.index', ['page' => 2]))->assertOk(), 'mine', 26, 1);
    }

    /** Seiten aus genau einer Tabelle füllen die Höhe. */
    public function test_single_table_pages_fill_the_viewport(): void {
        $this->admin->givePermissionTo([Permission::DomainViewAny->value, Permission::DomainAccountingView->value]);

        foreach (['asset-finance.index', 'contracts.index', 'crisis.index', 'damage-cases.index', 'disposal.index', 'rental.index', 'problem-reports.index', 'domains.index', 'domains.accounting', 'driver-license-checks.index', 'archive.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk()->assertSee(self::FILL, false);
        }
        foreach (['urlaub', 'bereitschaft', 'notdienst'] as $tab) {
            $this->get(route('archive.index', ['tab' => $tab]))->assertOk()->assertSee('wd-table-flex', false);
        }
    }

    private function assertPage(TestResponse $response, string $key, int $total, int $onPage): void {
        $paginator = $response->viewData($key);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame($total, $paginator->total());
        $this->assertCount($onPage, $paginator->items());
    }

    /** @return list<mixed> */
    private function column(TestResponse $response, string $key, string $attribute): array {
        return array_map(static fn (object $row): mixed => $row->{$attribute}, $response->viewData($key)->items());
    }
}
