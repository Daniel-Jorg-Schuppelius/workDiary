<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsDrilldownTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Customer, Expense, ExpenseCategory, MaterialUsage, Organization, Project, TimeEntry, Timesheet, User};
use App\Services\Reporting\EconomicsReportBuilder;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-332 Belegtiefe-Drilldown: signierte Quellposten-Listen (Zeit, Material,
 * Spesen/Belege) hinter den Kostenblöcken des Wirtschaftlichkeits-Reports —
 * Summen-Konsistenz gegen das Report-Aggregat (Hand-Fixtures), Signatur- und
 * Berechtigungspflicht, Org-Isolation, signierter Seitenwechsel.
 */
class EconomicsDrilldownTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        // Stundensatz 100 €/h, interner Kostensatz 40 €/h (RateCalculator-Snapshot).
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Drilldown GmbH',
            'hourly_rate' => 100,
            'internal_rate' => 40,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Drilldown-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Hand-Fixtures: 120 Min abrechenbar (Erlös 200 €, Kosten 80 €) + 30 Min
     * nicht abrechenbar (Kosten 20 €); Material 50 € abgerechnet + 10 € nicht;
     * Spesen 10 € abrechenbar + 5 € nicht abrechenbar (freigegeben) sowie 7 €
     * offen (zählt nicht).
     */
    private function seedSources(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 120,
            'billable' => true,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-11',
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 30,
            'billable' => false,
        ]);

        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-12',
            'status' => TimesheetStatus::Draft->value,
        ]);
        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Ersatzteil',
            'quantity' => 2,
            'unit' => 'Stk',
            'unit_price' => 25,
            'billed' => true,
        ]); // 50 €
        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Schrauben',
            'quantity' => 1,
            'unit' => 'Pck',
            'unit_price' => 10,
            'billed' => false,
        ]); // 10 €

        $category = ExpenseCategory::create([
            'organization_id' => $this->organization->id,
            'slug' => 'reise',
            'label' => 'Reisekosten',
        ]);
        Expense::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'expense_category_id' => $category->id,
            'date' => '2026-06-13',
            'description' => 'Parkgebühr',
            'vendor' => 'Parkhaus AG',
            'amount_net' => 10,
            'tax_rate' => 0,
            'billable' => true,
            'status' => ExpenseStatus::Approved->value,
        ]);
        Expense::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'expense_category_id' => $category->id,
            'date' => '2026-06-14',
            'description' => 'Maut',
            'amount_net' => 5,
            'tax_rate' => 0,
            'billable' => false,
            'status' => ExpenseStatus::Reimbursed->value,
        ]);
        Expense::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'expense_category_id' => $category->id,
            'date' => '2026-06-15',
            'description' => 'Noch offen',
            'amount_net' => 7,
            'tax_rate' => 0,
            'billable' => true,
            'status' => ExpenseStatus::Pending->value,
        ]); // nicht freigegeben → zählt nicht
    }

    /** @param array<string, string|float|int> $extra */
    private function signedUrl(string $kind, array $extra = []): string {
        return URL::temporarySignedRoute('reports.economics.drilldown', now()->addHour(), array_merge([
            'kind' => $kind,
            'project' => Sqid::encode(Project::class, $this->project->id),
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ], $extra));
    }

    public function test_time_drilldown_sums_match_report_aggregate(): void {
        $this->seedSources();

        $row = collect(app(EconomicsReportBuilder::class)->byProject(
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
            [(int) $this->project->id],
        ))->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(100.0, $row['costTime'], 0.01);
        $this->assertEqualsWithDelta(200.0, $row['revenueTime'], 0.01);

        $response = $this->actingAs($this->admin)->get($this->signedUrl('time', ['expected' => '100.00']));
        $response->assertOk();
        // Fußzeile == Report-Zellenwerte: 150 Min, 200,00 € Erlös, 100,00 € Kosten.
        $response->assertSee('150');
        $response->assertSee('200,00');
        $response->assertSee('100,00');
        // Konsistenz-Hinweis bleibt bei passendem expected aus.
        $response->assertDontSee('Konsistenz-Hinweis');
    }

    public function test_material_drilldown_sums_match_report_aggregate(): void {
        $this->seedSources();

        $response = $this->actingAs($this->admin)->get($this->signedUrl('material', ['expected' => '60.00']));
        $response->assertOk();
        $response->assertSee('Ersatzteil');
        $response->assertSee('60,00'); // Summe Direktaufwand 50 + 10
        $response->assertSee('50,00'); // davon abgerechnet (Erlös)
        $response->assertDontSee('Konsistenz-Hinweis');
    }

    public function test_expense_drilldown_lists_settled_receipts_with_category_and_link(): void {
        $this->seedSources();

        $response = $this->actingAs($this->admin)->get($this->signedUrl('expense', ['expected' => '15.00']));
        $response->assertOk();
        $response->assertSee('Parkgebühr');
        $response->assertSee('Parkhaus AG');
        $response->assertSee('Reisekosten');   // Kategorie
        $response->assertSee('Beleg öffnen');  // Verknüpfung zum Beleg
        $response->assertSee('15,00');         // Summe nur freigegebene/erstattete
        $response->assertDontSee('Noch offen'); // offene Spesen zählen nicht
    }

    public function test_consistency_hint_appears_when_expected_deviates(): void {
        $this->seedSources();

        $response = $this->actingAs($this->admin)->get($this->signedUrl('time', ['expected' => '999.00']));
        $response->assertOk();
        $response->assertSee('Konsistenz-Hinweis');
    }

    public function test_drilldown_requires_signature_and_report_permission(): void {
        $this->seedSources();

        $params = [
            'kind' => 'time',
            'project' => Sqid::encode(Project::class, $this->project->id),
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ];

        // Ohne Signatur: 403.
        $this->actingAs($this->admin)->get(route('reports.economics.drilldown', $params))->assertForbidden();

        // Gültige Signatur, aber ohne Report-Recht: 403.
        $this->actingAs($this->user)->get($this->signedUrl('time'))->assertForbidden();
    }

    public function test_drilldown_is_org_isolated(): void {
        $other = Organization::factory()->create();
        $foreignProject = Project::create([
            'organization_id' => $other->id,
            'name' => 'Fremdprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $url = URL::temporarySignedRoute('reports.economics.drilldown', now()->addHour(), [
            'kind' => 'time',
            'project' => Sqid::encode(Project::class, $foreignProject->id),
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]);

        // Org-Scope hart: fremdes Projekt ist für Org-A-Betrachter nicht auflösbar.
        $this->actingAs($this->admin)->get($url)->assertNotFound();
    }

    public function test_pagination_keeps_signature_valid_because_page_is_ignored(): void {
        $this->seedSources();

        $response = $this->actingAs($this->admin)->get($this->signedUrl('time') . '&page=2');
        $response->assertOk();
    }
}
