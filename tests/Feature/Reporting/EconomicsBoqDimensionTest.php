<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsBoqDimensionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Gaeb\{BoqItemType, BoqProgressSource};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{BillOfQuantity, BoqCostType, BoqItem, BoqItemCostApproach, BoqItemMapping, BoqItemProgress, Customer, DiaryEntry, Expense, Material, MaterialUsage, Project, TimeEntry, Timesheet, User};
use App\Services\Reporting\EconomicsReportBuilder;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * MVP-332 (Feature 014 × 049): LV-Dimension der Nachkalkulation — Kosten und
 * Erlöse je LV-Position (Ordnungszahl) aus Aufmaß + Positionszuordnungen,
 * Nachträge getrennt, Quellposten ohne Zuordnung als eigene Zeile (keine
 * stille Lücke; Hand-Fixtures wegen der SQLite-Falle nachgerechnet).
 */
class EconomicsBoqDimensionTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private EconomicsReportBuilder $builder;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->builder = app(EconomicsReportBuilder::class);

        $this->from = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $this->to = CarbonImmutable::parse('2026-06-30')->endOfDay();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Stundensatz 100 €/h, interner Kostensatz 40 €/h (RateCalculator-Snapshot).
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Baukunde GmbH',
            'hourly_rate' => 100,
            'internal_rate' => 40,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Bauprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Fixture: LV mit Normalposition 01.0010 (EP 12,50) und Nachtrag N1.0010
     * (EP 50). Quellposten:
     *  - 120 Min abrechenbar mit Bautagebuch-Link → Position 01.0010 (Kosten 80 €),
     *  - 30 Min ohne Link → ohne Zuordnung (Kosten 20 €),
     *  - Material 50 € direkt über Aufmaß-Meldung verknüpft → 01.0010,
     *  - Material 30 € über Material-Mapping → Nachtrag N1.0010,
     *  - Material 10 € ohne Verknüpfung + Spesen 10 € → ohne Zuordnung.
     * Aufmaß im Zeitraum: 60 Einheiten auf 01.0010 → Erlös 750 €.
     *
     * @return array{item: BoqItem, addendum: BoqItem}
     */
    private function seedBoqFixture(): array {
        $bill = BillOfQuantity::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'name' => 'LV Rohbau',
            'created_by' => $this->user->id,
        ]);

        $item = BoqItem::create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $bill->id,
            'reference_no' => '01.0010',
            'type' => BoqItemType::Standard->value,
            'short_text' => 'Mauerwerk KS 17,5',
            'quantity' => 100,
            'unit' => 'm2',
            'unit_price' => 12.5,
            'position' => 1,
        ]);

        $addendum = BoqItem::create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $bill->id,
            'reference_no' => 'N1.0010',
            'type' => BoqItemType::Standard->value,
            'short_text' => 'Nachtrag Kabeltrasse',
            'quantity' => 10,
            'unit' => 'm',
            'unit_price' => 50,
            'is_addendum' => true,
            'position' => 2,
        ]);

        $diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        // Aufmaß im Zeitraum: 60 × 12,50 € = 750 € Erlös auf 01.0010; der
        // Bautagebuch-Link ordnet die Zeitkosten zu.
        BoqItemProgress::create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => 60,
            'source' => BoqProgressSource::Measurement->value,
            'diary_entry_id' => $diary->id,
            'captured_at' => '2026-06-10 10:00:00',
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'diary_entry_id' => $diary->id,
            'date' => '2026-06-10',
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 120,
            'billable' => true,
        ]); // Kosten 80 € → 01.0010

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-11',
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 30,
            'billable' => false,
        ]); // Kosten 20 € → ohne Zuordnung

        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-12',
            'status' => TimesheetStatus::Draft->value,
        ]);

        $linkedUsage = MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kalksandstein',
            'quantity' => 2,
            'unit' => 'Pal',
            'unit_price' => 25,
            'billed' => true,
        ]); // 50 € → direkte Aufmaß-Verknüpfung auf 01.0010

        // Strukturelle Verknüpfung außerhalb des Zeitraums (Menge zählt nicht
        // ins Aufmaß des Zeitraums, die Kostenzuordnung bleibt bestehen).
        BoqItemProgress::create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => 5,
            'source' => BoqProgressSource::Material->value,
            'material_usage_id' => $linkedUsage->id,
            'captured_at' => '2026-05-01 08:00:00',
        ]);

        $material = Material::create([
            'organization_id' => $this->organization->id,
            'name' => 'NYM-Kabel',
            'unit' => 'm',
        ]);

        BoqItemMapping::create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $addendum->id,
            'mappable_type' => Material::class,
            'mappable_id' => $material->id,
            'factor' => 1,
        ]);

        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'material_id' => $material->id,
            'description' => 'Kabel',
            'quantity' => 3,
            'unit' => 'm',
            'unit_price' => 10,
            'billed' => false,
        ]); // 30 € → Mapping auf Nachtrag N1.0010

        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kleinmaterial',
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => 10,
            'billed' => false,
        ]); // 10 € → ohne Zuordnung

        Expense::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => '2026-06-15',
            'description' => 'Parkgebühr Baustelle',
            'amount_net' => 10,
            'tax_rate' => 0,
            'billable' => true,
            'status' => ExpenseStatus::Approved->value,
        ]); // 10 € → ohne Zuordnung (Spesen tragen keinen LV-Anker)

        return ['item' => $item, 'addendum' => $addendum];
    }

    public function test_by_boq_position_attributes_revenue_and_costs_per_reference_no(): void {
        $fixture = $this->seedBoqFixture();

        $result = $this->builder->byBoqPosition($this->from, $this->to, (int) $this->project->id);

        $this->assertTrue($result['hasBoq']);
        $positions = collect($result['positions']);
        $this->assertCount(2, $positions);

        $main = $positions->firstWhere('referenceNo', '01.0010');
        $this->assertNotNull($main);
        $this->assertSame((int) $fixture['item']->id, $main['boqItemId']);
        $this->assertFalse($main['isAddendum']);
        // Aufmaß: nur die 60 Einheiten IM Zeitraum (die 5 aus Mai zählen nicht).
        $this->assertEqualsWithDelta(60.0, $main['measuredQuantity'], 0.0001);
        $this->assertEqualsWithDelta(750.0, $main['revenue'], 0.01); // 60 × 12,50
        $this->assertSame(120, $main['timeMinutes']);
        $this->assertEqualsWithDelta(80.0, $main['costTime'], 0.01); // 120 Min @40/h
        $this->assertEqualsWithDelta(50.0, $main['costMaterial'], 0.01); // direkte Verknüpfung
        $this->assertEqualsWithDelta(130.0, $main['cost'], 0.01);
        $this->assertEqualsWithDelta(620.0, $main['contribution'], 0.01);

        $addendum = $positions->firstWhere('referenceNo', 'N1.0010');
        $this->assertNotNull($addendum);
        $this->assertTrue($addendum['isAddendum']);
        $this->assertEqualsWithDelta(0.0, $addendum['revenue'], 0.01); // kein Aufmaß im Zeitraum
        $this->assertEqualsWithDelta(30.0, $addendum['costMaterial'], 0.01); // Material-Mapping
        $this->assertEqualsWithDelta(-30.0, $addendum['contribution'], 0.01);
    }

    /**
     * Plan-Ist je Position (Feature 109 → 014): Die Kalkulation gilt der
     * **vollen** LV-Menge, die Ist-Kosten fallen für die **ausgeführte** an.
     * Beide unskaliert zu vergleichen wiese jeden unfertigen Abschnitt als
     * Ersparnis aus.
     */
    public function test_calculation_is_scaled_to_the_measured_quantity(): void {
        ['item' => $item] = $this->seedBoqFixture();
        $bill = $item->billOfQuantity;

        BoqCostType::create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $bill->id,
            'cost_key' => 'LO',
            'description' => 'Lohn',
            'markup_percent' => '25.000000',
            'position' => 1,
        ]);
        // 10 × 80 € = 800 € EKT + 25 % = 1.000 € für 100 m² → 10 €/m².
        BoqItemCostApproach::create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'cost_key' => 'LO',
            'quantity' => '10.000',
            'value' => '80.000',
            'position' => 1,
        ]);

        $result = $this->builder->byBoqPosition($this->from, $this->to, (int) $this->project->id);

        $this->assertTrue($result['hasCalculation']);
        // Aufgemessen sind 60 m² → 600 €, nicht die vollen 1.000 €.
        $row = collect($result['positions'])->firstWhere('referenceNo', '01.0010');
        $this->assertSame(600.0, $row['calculated']);
        // Ist 130 € (80 Zeit + 50 Material) gegen 600 € kalkuliert.
        $this->assertSame(-470.0, $row['calcDelta']);

        // Der Nachtrag trägt keine Ansätze — dort gibt es nichts zu vergleichen.
        $addendumRow = collect($result['positions'])->firstWhere('referenceNo', 'N1.0010');
        $this->assertNull($addendumRow['calculated']);
        $this->assertNull($addendumRow['calcDelta']);
    }

    /** Ohne Kalkulationsdaten bleibt die Spalte weg statt bei 0 € zu stehen. */
    public function test_without_calculation_data_the_column_stays_out(): void {
        $this->seedBoqFixture();

        $result = $this->builder->byBoqPosition($this->from, $this->to, (int) $this->project->id);

        $this->assertFalse($result['hasCalculation']);
        $this->assertFalse($result['calculationImported']);
        $this->assertNull($result['positions'][0]['calculated']);
    }

    public function test_unassigned_row_carries_unlinked_sources_and_reconciles_with_project_costs(): void {
        $this->seedBoqFixture();

        $result = $this->builder->byBoqPosition($this->from, $this->to, (int) $this->project->id);

        $u = $result['unassigned'];
        $this->assertSame(30, $u['timeMinutes']);
        $this->assertEqualsWithDelta(20.0, $u['costTime'], 0.01);
        $this->assertEqualsWithDelta(10.0, $u['costMaterial'], 0.01);
        $this->assertEqualsWithDelta(10.0, $u['costExpense'], 0.01);
        $this->assertEqualsWithDelta(40.0, $u['cost'], 0.01);

        // Invariante (keine stille Lücke): Positions-Kosten + „ohne Zuordnung"
        // == Projektkosten aus byProject (Kosten 100 Zeit + 90 Material + 10 Spesen).
        $projectRow = collect($this->builder->byProject($this->from, $this->to, [(int) $this->project->id]))->first();
        $this->assertNotNull($projectRow);
        $positionCost = collect($result['positions'])->sum('cost');
        $this->assertEqualsWithDelta((float) $projectRow['cost'], $positionCost + $u['cost'], 0.01);
        $this->assertEqualsWithDelta(200.0, $positionCost + $u['cost'], 0.01);
    }

    public function test_has_boq_false_for_project_without_bill_of_quantities(): void {
        $result = $this->builder->byBoqPosition($this->from, $this->to, (int) $this->project->id);

        $this->assertFalse($result['hasBoq']);
        $this->assertSame([], $result['positions']);
        $this->assertEqualsWithDelta(0.0, $result['unassigned']['cost'], 0.01);
    }

    public function test_report_page_renders_lv_section_with_addendum_group_and_unassigned_row(): void {
        $this->seedBoqFixture();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-30'))
            ->get(route('reports.economics', ['project_id' => Sqid::encode(Project::class, $this->project->id)]));

        $response->assertOk();
        $response->assertSee('Nachkalkulation je LV-Position');
        $response->assertSee('01.0010');
        $response->assertSee('N1.0010');
        $response->assertSee('Nachträge');
        $response->assertSee('Ohne LV-Zuordnung');
    }

    public function test_report_page_shows_empty_state_for_project_without_lv(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-30'))
            ->get(route('reports.economics', ['project_id' => Sqid::encode(Project::class, $this->project->id)]));

        $response->assertOk();
        $response->assertSee('Dieses Projekt führt kein Leistungsverzeichnis.');
    }

    public function test_csv_export_appends_lv_section_only_with_project_filter(): void {
        $this->seedBoqFixture();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $withProject = $this->actingAs($admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-30'))
            ->get(route('reports.economics', [
                'project_id' => Sqid::encode(Project::class, $this->project->id),
                'export' => 'csv',
            ]));
        $withProject->assertOk();
        $csv = (string) $withProject->getContent();
        $this->assertStringContainsString('LVPosition;Nachtrag;Kurztext', $csv);
        $this->assertStringContainsString('01.0010', $csv);
        $this->assertStringContainsString('(ohne Zuordnung)', $csv);

        $withoutProject = $this->actingAs($admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-30'))
            ->get(route('reports.economics', ['export' => 'csv']));
        $withoutProject->assertOk();
        $this->assertStringNotContainsString('LVPosition', (string) $withoutProject->getContent());
    }
}
