<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportExportPermissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Finance\ProfitDetermination;
use App\Enums\User\Permission;
use App\Http\Controllers\Finance\{AccountingBudgetController, AccountingReportController};
use App\Http\Controllers\Helpdesk\SlaReportController;
use App\Http\Controllers\Learning\LearningReportController;
use App\Http\Controllers\Procedure\ProcedureBlockedRunsController;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Services\Accounting\{AccountingProfileService, FiscalYearService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Recht „Auswertungen exportieren“ (E10/E28, MVP-1101): Berichte im Menü
 * „Auswertungen“ — auch die anderer Module — nur mit `report.export` oder als
 * Admin, persönliche Exporte (eigene Daten) frei; ohne Recht keine
 * Exportknöpfe. Fachberichte anderer Menüs folgen ihrem Fachrecht.
 */
class ReportExportPermissionTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array<string, array{string}> */
    public static function formats(): array {
        return ['CSV' => ['csv'], 'Excel' => ['xlsx'], 'PDF' => ['pdf']];
    }

    #[DataProvider('formats')]
    public function test_team_report_export_requires_the_export_permission(string $format): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [Permission::ReportView]);

        $this->actingAs($viewer)->get(route('reports.customers', ['export' => $format]))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['event' => 'report.exported']);

        $this->grantPermissions($viewer, [Permission::ReportExport]);
        $this->actingAs($viewer)->get(route('reports.customers', ['export' => $format]))->assertOk();
    }

    public function test_admin_exports_without_the_permission(): void {
        $this->actingAs($this->orgAdmin())->get(route('reports.customers', ['export' => 'csv']))->assertOk();
    }

    public function test_export_buttons_follow_the_permission(): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [Permission::ReportView]);

        $this->actingAs($viewer)->get(route('reports.customers'))
            ->assertOk()
            ->assertDontSee('export=csv', false)
            ->assertDontSee('export=pdf', false);

        $this->grantPermissions($viewer, [Permission::ReportExport]);
        $this->actingAs($viewer)->get(route('reports.customers'))
            ->assertOk()
            ->assertSee('export=csv', false)
            ->assertSee('export=pdf', false);
    }

    public function test_my_month_stays_free(): void {
        $user = $this->orgUser();

        $this->actingAs($user)->withSession($this->dateRangeMonth(2030, 4))
            ->get(route('reports.my-month', ['export' => 'csv']))->assertOk();
        $this->actingAs($user)->withSession($this->dateRangeMonth(2030, 4))
            ->get(route('reports.my-month'))->assertOk()->assertSee('export=csv', false);
    }

    public function test_own_view_is_free_but_team_view_needs_the_permission(): void {
        $plain = $this->orgUser();
        $this->actingAs($plain)->get(route('reports.attendance', ['scope' => 'mine', 'export' => 'csv']))->assertOk();
        // Ohne Teamsicht fällt „team“ auf die eigene Sicht zurück — der Export bleibt frei.
        $this->actingAs($plain)->get(route('reports.attendance', ['scope' => 'team', 'export' => 'csv']))->assertOk();

        $lead = $this->orgUser();
        $this->grantPermissions($lead, [Permission::AttendanceViewAny]);
        $this->actingAs($lead)->get(route('reports.attendance', ['scope' => 'mine', 'export' => 'csv']))->assertOk();
        $this->actingAs($lead)->get(route('reports.attendance', ['scope' => 'team', 'export' => 'csv']))->assertForbidden();
        $this->actingAs($lead)->get(route('reports.attendance', ['scope' => 'team']))
            ->assertOk()->assertDontSee('export=csv', false);

        $this->grantPermissions($lead, [Permission::ReportExport]);
        $this->actingAs($lead)->get(route('reports.attendance', ['scope' => 'team', 'export' => 'csv']))->assertOk();
    }

    public function test_work_balance_of_another_person_needs_the_permission(): void {
        $lead = $this->orgUser();
        $other = $this->orgUser();
        $this->grantPermissions($lead, [Permission::TimeEntryViewAny]);

        $this->actingAs($lead)->get(route('reports.work-balance', ['export' => 'pdf']))->assertOk();
        $this->actingAs($lead)
            ->get(route('reports.work-balance', ['user' => Sqid::encode(User::class, (int) $other->id), 'export' => 'pdf']))
            ->assertForbidden();
    }

    public function test_excel_export_outside_csv_path_is_checked_too(): void {
        $lead = $this->orgUser();
        $this->grantPermissions($lead, [Permission::TimeEntryViewAny]);

        $this->actingAs($lead)->get(route('reports.customer-project', ['scope' => 'team', 'export' => 'xlsx']))->assertForbidden();
        $this->actingAs($lead)->get(route('reports.customer-project', ['scope' => 'mine', 'export' => 'xlsx']))->assertOk();
    }

    public function test_logbook_of_own_company_car_is_free_pool_vehicle_is_not(): void {
        $driver = $this->orgUser();
        $this->grantPermissions($driver, [Permission::VehicleManage]);
        $own = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'default_user_id' => $driver->id]);
        $pool = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'default_user_id' => null]);

        $this->actingAs($driver)->get(route('reports.logbook', ['vehicle' => $own->sqid, 'export' => 'csv']))->assertOk();
        $this->actingAs($driver)->get(route('reports.logbook', ['vehicle' => $pool->sqid, 'export' => 'csv']))->assertForbidden();
    }

    public function test_emergency_list_export_follows_its_own_right(): void {
        $warden = $this->orgUser();
        $this->grantPermissions($warden, [Permission::ReportPresenceEmergency]);

        $this->actingAs($warden)->get(route('reports.presence-emergency', ['export' => 'csv']))->assertOk();
        $this->actingAs($warden)->get(route('reports.presence-emergency', ['export' => 'pdf']))->assertOk();
    }

    /** @return array<string, array{string, Permission, string}> */
    public static function menuReportsOfOtherModules(): array {
        return [
            'SLA CSV' => ['reports.sla', Permission::SlaViewAny, 'csv'],
            'SLA PDF' => ['reports.sla', Permission::SlaViewAny, 'pdf'],
            'Lernen CSV' => ['reports.learning', Permission::LearningViewAny, 'csv'],
            'Lernen PDF' => ['reports.learning', Permission::LearningViewAny, 'pdf'],
            'Blockierte Prozedurläufe Excel' => ['reports.procedure-blocked', Permission::ProcedureRunView, 'xlsx'],
        ];
    }

    #[DataProvider('menuReportsOfOtherModules')]
    public function test_menu_reports_of_other_modules_need_the_export_permission(string $route, Permission $right, string $format): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [$right]);

        $this->actingAs($viewer)->get(route($route))->assertOk()->assertDontSee('export=' . $format, false);
        $this->actingAs($viewer)->get(route($route, ['export' => $format]))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['event' => 'report.exported']);

        $this->grantPermissions($viewer, [Permission::ReportExport]);
        $this->actingAs($viewer)->get(route($route))->assertOk()->assertSee('export=' . $format, false);
        $this->actingAs($viewer)->get(route($route, ['export' => $format]))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.exported', 'user_id' => $viewer->id]);
    }

    public function test_admin_exports_menu_reports_of_other_modules(): void {
        $admin = $this->orgAdmin();

        foreach (['reports.sla', 'reports.learning', 'reports.procedure-blocked'] as $route) {
            $this->actingAs($admin)->get(route($route, ['export' => 'csv']))->assertOk();
        }
    }

    public function test_accounting_reports_need_the_export_permission(): void {
        $this->activateLocalLedger();
        $bookkeeper = $this->orgUser();
        $this->grantPermissions($bookkeeper, [Permission::AccountingLedgerView]);

        $exports = [
            ['reports.accounting.trial-balance', [], 'pdf'],
            ['reports.accounting.replacement-forecast', ['years' => 5], 'csv'],
            ['reports.accounting.budget.index', [], 'xlsx'],
        ];
        foreach ($exports as [$route, $params, $format]) {
            $this->actingAs($bookkeeper)->get(route($route, $params))->assertOk()->assertDontSee('export=' . $format, false);
            $this->actingAs($bookkeeper)->get(route($route, $params + ['export' => $format]))->assertForbidden();
        }

        $this->grantPermissions($bookkeeper, [Permission::ReportExport]);
        foreach ($exports as [$route, $params, $format]) {
            $this->actingAs($bookkeeper)->get(route($route, $params))->assertOk()->assertSee('export=' . $format, false);
            $this->actingAs($bookkeeper)->get(route($route, $params + ['export' => $format]))->assertOk();
        }

        $this->actingAs($this->orgAdmin())->get(route('reports.accounting.replacement-forecast', ['export' => 'pdf']))->assertOk();
    }

    public function test_domain_report_outside_the_menu_keeps_its_own_right(): void {
        $clerk = $this->orgUser();
        $this->grantPermissions($clerk, [Permission::ClaimViewAny]);

        $this->actingAs($clerk)->get(route('claims.reports.index'))->assertOk()->assertSee('export=csv', false);
        $this->actingAs($clerk)->get(route('claims.reports.index', ['export' => 'csv']))->assertOk();
    }

    /**
     * Wache: Jeder Export unter den Routen der Auswertungen (`reports.*`)
     * verlangt das Recht — keiner dieser Controller nimmt sich aus.
     */
    public function test_no_report_route_opts_out_of_the_export_permission(): void {
        $checked = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $controller = $route->getControllerClass();
            if (! str_starts_with((string) $route->getName(), 'reports.') || $controller === null || ! method_exists($controller, 'exportNeedsReportPermission')) {
                continue;
            }
            $declaredIn = (string) (new \ReflectionMethod($controller, 'exportNeedsReportPermission'))->getFileName();
            $this->assertStringEndsWith(class_basename(WritesReportCsv::class) . '.php', $declaredIn, $route->getName() . ' nimmt sich vom Exportrecht aus.');
            $checked[$controller] = true;
        }

        foreach ([SlaReportController::class, LearningReportController::class, ProcedureBlockedRunsController::class, AccountingReportController::class, AccountingBudgetController::class] as $controller) {
            $this->assertArrayHasKey($controller, $checked);
        }
    }

    private function activateLocalLedger(): void {
        $startsOn = CarbonImmutable::now()->startOfYear();
        app(AccountingProfileService::class)->configure($this->organization, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->organization, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->organization, $this->orgAdmin());
    }

    public function test_csv_headers_and_fixed_cells_follow_the_user_locale(): void {
        $admin = $this->orgAdmin();
        $admin->setPreference('locale', 'fr');

        $csv = (string) $this->actingAs($admin)
            ->get(route('reports.attendance', ['scope' => 'team', 'export' => 'csv']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Employé;Jours ouvrés;Cible (min)', $csv);
        $this->assertStringContainsString("\r\nTotal;", $csv);
        $this->assertStringNotContainsString('Mitarbeiter', $csv);
        $this->assertStringNotContainsString('GESAMT', $csv);

        $blocked = (string) $this->actingAs($admin)
            ->get(route('reports.procedure-blocked', ['export' => 'csv']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Motif du blocage;Procédure', $blocked);
        $this->assertStringNotContainsString('Sperrgrund', $blocked);
    }
}
