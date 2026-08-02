<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerValueReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Reporting\CustomerValueReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Kundenwert & Portfolio (MVP-465): RFM-Quintile mit fünf aktiven Kunden
 * (Scores 1–5 exakt), sequenzielle Segmentregeln, Konzentration (Top-N/HHI)
 * und die Risikoliste gefährdeter A-Kunden; dazu Berechtigungs-Gate.
 *
 * Fixture (Zeitraum 2030-01-01 – 2030-06-30, Erlös = billable rate-Snapshots):
 *  - Alpha:   Erstleistung 2029, Recency 5,   5 Aktivitätstage, 10 000 € → Champion
 *  - Bravo:   Erstleistung 2029, Recency 170, 4 Aktivitätstage,  8 000 € → Gefährdet
 *  - Charlie: Erstleistung 2029, Recency 10,  3 Aktivitätstage,  5 000 € → Stammkunde
 *  - Delta:   Erstleistung 2029, Recency 20,  2 Aktivitätstage,  1 000 € → Inaktiv (R ≤ 2)
 *  - Echo:    Erstleistung IM Zeitraum, 1 Tag, 300 €                    → Neu
 *  - Foxtrot: keine Leistung im Zeitraum                                → Inaktiv
 */
class CustomerValueReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    /** @var array<string, Customer> */
    private array $customers = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // [Name, Aktivitätstage im Zeitraum (letzter zuerst), Erlös, Vorleistung 2029?]
        $spec = [
            'Alpha' => [['2030-06-25', '2030-06-01', '2030-05-01', '2030-04-01', '2030-03-01'], 10000.0, true],
            'Bravo' => [['2030-01-11', '2030-01-08', '2030-01-05', '2030-01-02'], 8000.0, true],
            'Charlie' => [['2030-06-20', '2030-05-15', '2030-04-15'], 5000.0, true],
            'Delta' => [['2030-06-10', '2030-06-05'], 1000.0, true],
            'Echo' => [['2030-06-27'], 300.0, false],
            'Foxtrot' => [[], 0.0, false],
        ];

        foreach ($spec as $name => [$days, $revenue, $preExisting]) {
            $customer = Customer::create([
                'organization_id' => $this->organization->id,
                'name' => $name . ' GmbH',
            ]);
            $this->customers[$name] = $customer;
            if ($days === [] && ! $preExisting) {
                continue;
            }
            $project = Project::create([
                'organization_id' => $this->organization->id,
                'customer_id' => $customer->id,
                'name' => 'Projekt ' . $name,
                'status' => ProjectStatus::Active->value,
                'created_by' => $this->admin->id,
            ]);
            if ($preExisting) {
                $this->createTimeEntry($project, '2029-05-02', null);
            }
            foreach ($days as $i => $day) {
                // Der erste Tag trägt den vollen Erlös-Snapshot, weitere Tage 0.
                $this->createTimeEntry($project, $day, $i === 0 ? $revenue : null);
            }
        }
    }

    private function createTimeEntry(Project $project, string $date, ?float $rate): void {
        // rate wird vom Modell-Hook via RateCalculator berechnet — bei
        // 1-h-Einträgen entspricht der Snapshot dem hourly_rate.
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => $date,
            'started_at' => $date . ' 09:00:00',
            'ended_at' => $date . ' 10:00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => $rate,
        ]);
    }

    private function build(): array {
        return app(CustomerValueReportBuilder::class)->build(
            CarbonImmutable::parse('2030-01-01'),
            CarbonImmutable::parse('2030-06-30'),
            null,
            null,
        );
    }

    public function test_rfm_segments_follow_sequential_rules(): void {
        $result = $this->build();
        $byName = collect($result['rows'])->keyBy('customerName');

        $this->assertSame('champion', $byName['Alpha GmbH']['segment']);
        $this->assertSame('at_risk', $byName['Bravo GmbH']['segment']);
        $this->assertSame('loyal', $byName['Charlie GmbH']['segment']);
        $this->assertSame('inactive', $byName['Delta GmbH']['segment']);
        $this->assertSame('new', $byName['Echo GmbH']['segment']);
        $this->assertSame('inactive', $byName['Foxtrot GmbH']['segment']);

        // Quintile mit n=5 sind exakt 1–5 (Monetary-Rangfolge).
        $this->assertSame(5, $byName['Alpha GmbH']['m']);
        $this->assertSame(4, $byName['Bravo GmbH']['m']);
        $this->assertSame(1, $byName['Echo GmbH']['m']);
        // Recency invertiert: kürzeste Inaktivität = 5.
        $this->assertSame(1, $byName['Bravo GmbH']['r']);
        $this->assertNull($byName['Foxtrot GmbH']['m']);
    }

    public function test_recency_and_first_activity_are_org_wide_facts(): void {
        $result = $this->build();
        $byName = collect($result['rows'])->keyBy('customerName');

        // 2030-06-25 → 2030-06-30 = 5 Tage; Erstleistung liegt vor dem Zeitraum.
        $this->assertSame(5, $byName['Alpha GmbH']['recencyDays']);
        $this->assertSame('2029-05-02', $byName['Alpha GmbH']['firstActivity']);
        $this->assertSame(5, $byName['Alpha GmbH']['frequencyDays']);
        $this->assertNull($byName['Foxtrot GmbH']['recencyDays']);
    }

    public function test_concentration_shares_and_hhi(): void {
        $result = $this->build();
        $c = $result['concentration'];

        $this->assertSame(24300.0, $c['totalRevenue']);
        $this->assertSame(100.0, $c['top5Share']);
        $this->assertSame(100.0, $c['top10Share']);
        // HHI = Σ (Anteil in %)² über 10 000/8 000/5 000/1 000/300 von 24 300.
        $this->assertEqualsWithDelta(3219, $c['hhi'], 1);
        $this->assertSame(5, $c['activeCustomers']);
    }

    public function test_risk_rows_require_high_monetary_and_stale_recency(): void {
        $builder = app(CustomerValueReportBuilder::class);
        $result = $this->build();

        $risk = $builder->riskRows($result['rows'], 60);
        $this->assertCount(1, $risk);
        $this->assertSame('Bravo GmbH', $risk[0]['customerName']);

        // Mit sehr hoher Schwelle fällt auch Bravo heraus.
        $this->assertSame([], $builder->riskRows($result['rows'], 365));
    }

    public function test_report_requires_report_view_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-06-30'))
            ->get(route('reports.customer-value'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-06-30'))
            ->get(route('reports.customer-value'))
            ->assertOk()
            ->assertSee('Gefährdete A-Kunden');
    }

    public function test_csv_export_contains_segments_and_metadata(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-06-30'))
            ->get(route('reports.customer-value', ['export' => 'csv']));

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringContainsString('#report:customer-value', $content);
        $this->assertStringContainsString('Bravo GmbH', $content);
        $this->assertStringContainsString('Kunde;Segment', $content);
    }
}
