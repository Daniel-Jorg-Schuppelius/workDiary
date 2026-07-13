<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstReportBuilderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Attendance, Customer, DiaryEntry, Project, ScheduledShift, ShiftType, Site, TimeEntry, User, WorkSchedule};
use App\Models\Location\{CustomerGeofence, LocationVisit};
use App\Services\Reporting\PlanIstReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PlanIstReportBuilderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private PlanIstReportBuilder $builder;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->builder = app(PlanIstReportBuilder::class);
    }

    public function test_presence_calculates_plan_actual_and_warnings(): void {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        WorkSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'weekly_minutes' => 2400, // 40h
            'daily_target_minutes' => 480, // 8h
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '08:00:00',
            'core_end' => '16:30:00',
            'frame_start' => '06:00:00',
            'frame_end' => '20:00:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);

        // Mo 15.01.2024 — ISO weekday 1, working day.
        // Stempelung 08:25 (Δ +25 min) … 16:30, brutto 8:05, brutto>6h → 30 min Pause → netto 7:35 = 455 min.
        Attendance::withoutEvents(function () use ($user) {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => '2024-01-15',
                'started_at' => '2024-01-15 08:25:00',
                'ended_at' => '2024-01-15 16:30:00',
                'duration_minutes' => 455,
            ]);
        });

        $rows = $this->builder->presenceFor(
            $user,
            CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now(),
            CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now(),
        );

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(480, $row['plan_minutes']);
        $this->assertGreaterThan(0, $row['actual_minutes']);
        $this->assertSame(25, $row['late_start_minutes']);
        $this->assertContains('presence.lateStart', $row['warnings']);
        $this->assertFalse($row['no_plan']);
    }

    public function test_presence_marks_days_outside_schedule_as_no_plan(): void {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        WorkSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '08:00:00',
            'core_end' => '16:30:00',
            'frame_start' => '06:00:00',
            'frame_end' => '20:00:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);

        // 2024-01-13 ist ein Samstag → ISO 6 → not working day.
        $rows = $this->builder->presenceFor(
            $user,
            CarbonImmutable::create(2024, 1, 13) ?? CarbonImmutable::now(),
            CarbonImmutable::create(2024, 1, 13) ?? CarbonImmutable::now(),
        );

        $this->assertTrue($rows[0]['no_plan']);
        $this->assertSame(0, $rows[0]['plan_minutes']);
        $this->assertEmpty($rows[0]['warnings']);
    }

    // ── A14 · MVP-333: Schicht-Dimension (§2.3) ─────────────────────────────

    private function attendance(User $user, string $date, string $start, string $end, int $minutes): void {
        Attendance::withoutEvents(function () use ($user, $date, $start, $end, $minutes): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $start,
                'ended_at' => $end,
                'duration_minutes' => $minutes,
            ]);
        });
    }

    public function test_shift_dimension_aggregates_plan_and_overlap_actual_per_type(): void {
        $type = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Frühdienst',
            'default_start_time' => '08:00',
            'default_end_time' => '16:00',
        ]);

        $a = User::factory()->create(['organization_id' => $this->organization->id]);
        $b = User::factory()->create(['organization_id' => $this->organization->id]);
        $c = User::factory()->create(['organization_id' => $this->organization->id]);

        // Soll: 2 × 8 h = 960 min (published + confirmed); Draft/Cancelled zählen nicht.
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $a->id,
            'shift_type_id' => $type->id,
            'date' => '2024-01-15',
        ]);
        ScheduledShift::factory()->confirmed()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $b->id,
            'shift_type_id' => $type->id,
            'date' => '2024-01-15',
        ]);
        ScheduledShift::factory()->create([ // draft
            'organization_id' => $this->organization->id,
            'user_id' => $c->id,
            'shift_type_id' => $type->id,
            'date' => '2024-01-15',
        ]);
        ScheduledShift::factory()->cancelled()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $c->id,
            'shift_type_id' => $type->id,
            'date' => '2024-01-15',
        ]);

        // Ist: A deckt das Fenster voll (480), B stempelt 10–18 Uhr → Überlappung
        // mit 08–16 Uhr = 360. Handrechnung: Plan 960, Ist 840, Δ −120, 87,5 %.
        $this->attendance($a, '2024-01-15', '2024-01-15 08:00:00', '2024-01-15 16:00:00', 480);
        $this->attendance($b, '2024-01-15', '2024-01-15 10:00:00', '2024-01-15 18:00:00', 480);

        $from = CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now();
        $report = $this->builder->shiftFor($from, $from);

        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];
        $this->assertSame($type->id, $row['shift_type_id']);
        $this->assertSame(2, $row['shifts']);
        $this->assertSame(960, $row['plan_minutes']);
        $this->assertSame(840, $row['actual_minutes']);
        $this->assertSame(-120, $row['delta_minutes']);
        $this->assertSame(87.5, $row['coverage_pct']);

        $this->assertSame([['key' => '2024-01-15', 'plan_minutes' => 960, 'actual_minutes' => 840, 'delta_minutes' => -120]], $report['buckets']);
        $this->assertSame(960, $report['totals']['plan_minutes']);
        $this->assertSame(840, $report['totals']['actual_minutes']);
        $this->assertSame(87.5, $report['totals']['coverage_pct']);
    }

    public function test_shift_dimension_handles_overnight_windowless_and_week_buckets(): void {
        $night = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nachtdienst',
            'default_start_time' => '22:00',
            'default_end_time' => '06:00',
        ]);

        $a = User::factory()->create(['organization_id' => $this->organization->id]);
        $d = User::factory()->create(['organization_id' => $this->organization->id]);

        // Übernacht: Fenster 15.01. 22:00 – 16.01. 06:00 → Plan 480; Anwesenheit deckt voll.
        ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $a->id,
            'shift_type_id' => $night->id,
            'date' => '2024-01-15',
        ]);
        $this->attendance($a, '2024-01-15', '2024-01-15 22:00:00', '2024-01-16 06:00:00', 480);

        // Ohne Zeitfenster (kein Typ, keine Zeiten): Soll = 0, Ist = Tages-
        // Anwesenheit — bei zwei Schichten derselben Person nur EINMAL gezählt.
        ScheduledShift::factory()->published()->count(2)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $d->id,
            'shift_type_id' => null,
            'date' => '2024-01-16',
        ]);
        $this->attendance($d, '2024-01-16', '2024-01-16 08:00:00', '2024-01-16 13:00:00', 300);

        $from = CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now();
        $to = CarbonImmutable::create(2024, 1, 16) ?? CarbonImmutable::now();
        $report = $this->builder->shiftFor($from, $to);

        $byName = collect($report['rows'])->keyBy('name');
        $nightRow = $byName->get('Nachtdienst');
        $this->assertNotNull($nightRow);
        $this->assertSame(480, $nightRow['plan_minutes']);
        $this->assertSame(480, $nightRow['actual_minutes']);
        $this->assertSame(100.0, $nightRow['coverage_pct']);

        $windowless = $byName->get(__('Ohne Schichttyp'));
        $this->assertNotNull($windowless);
        $this->assertSame(2, $windowless['shifts']);
        $this->assertSame(2, $windowless['without_window']);
        $this->assertSame(0, $windowless['plan_minutes']);
        $this->assertSame(300, $windowless['actual_minutes']); // nicht 600
        $this->assertNull($windowless['coverage_pct']);

        // Wochen-Bucket: 15./16.01.2024 liegen beide in ISO-Woche 2024-W03.
        $weekly = $this->builder->shiftFor($from, $to, 'week');
        $this->assertCount(1, $weekly['buckets']);
        $this->assertSame('2024-W03', $weekly['buckets'][0]['key']);
        $this->assertSame(480, $weekly['buckets'][0]['plan_minutes']);
        $this->assertSame(780, $weekly['buckets'][0]['actual_minutes']);
    }

    // ── A14 · MVP-333: Projekt-Dimension (§2.2) ─────────────────────────────

    public function test_project_dimension_uses_planned_minutes_and_marks_no_plan(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $p1 = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Projekt Alpha']);
        $p2 = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Projekt Beta']);

        // Soll P1: ein geplanter (600) + ein ungeplanter Auftrag im Zeitraum;
        // ein geplanter Auftrag AUSSERHALB (999) darf nicht zählen.
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $p1->id,
            'planned_minutes' => 600,
            'start_at' => '2024-01-10 08:00:00',
            'end_at' => '2024-01-10 10:00:00',
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $p1->id,
            'planned_minutes' => null,
            'start_at' => '2024-01-11 08:00:00',
            'end_at' => '2024-01-11 10:00:00',
        ]);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $p1->id,
            'planned_minutes' => 999,
            'start_at' => '2023-11-01 08:00:00',
            'end_at' => '2023-11-01 10:00:00',
        ]);

        // Ist: P1 = 300 abrechenbar + 120 nicht; P2 = 100; ohne Projekt = 50.
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'project_id' => $p1->id, 'date' => '2024-01-12', 'minutes' => 300, 'billable' => true]);
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'project_id' => $p1->id, 'date' => '2024-01-12', 'minutes' => 120, 'billable' => false]);
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'project_id' => $p2->id, 'date' => '2024-01-13', 'minutes' => 100, 'billable' => false]);
        TimeEntry::factory()->administration()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'date' => '2024-01-13', 'minutes' => 50, 'billable' => false]);

        $from = CarbonImmutable::create(2024, 1, 1) ?? CarbonImmutable::now();
        $to = CarbonImmutable::create(2024, 1, 31) ?? CarbonImmutable::now();
        $report = $this->builder->projectTimeFor($from, $to);

        $byName = collect($report['rows'])->keyBy('name');

        $alpha = $byName->get('Projekt Alpha');
        $this->assertNotNull($alpha);
        $this->assertSame(600, $alpha['plan_minutes']);
        $this->assertSame(420, $alpha['actual_minutes']);
        $this->assertSame(300, $alpha['billable_minutes']);
        $this->assertSame(-180, $alpha['delta_minutes']);
        $this->assertSame(2, $alpha['orders']);
        $this->assertSame(1, $alpha['planned_orders']);
        $this->assertFalse($alpha['no_plan']);
        $this->assertSame($customer->name, $alpha['customer']);

        $beta = $byName->get('Projekt Beta');
        $this->assertNotNull($beta);
        $this->assertSame(0, $beta['plan_minutes']);
        $this->assertSame(100, $beta['actual_minutes']);
        $this->assertTrue($beta['no_plan']); // Konzept §2.2: noPlan, kein Alarm

        $unassigned = $byName->get(__('Ohne Projekt'));
        $this->assertNotNull($unassigned);
        $this->assertSame(50, $unassigned['actual_minutes']);
        $this->assertTrue($unassigned['no_plan']);

        // Summen == Handrechnung: Plan 600, Ist 420+100+50 = 570, Δ −30.
        $this->assertSame(600, $report['totals']['plan_minutes']);
        $this->assertSame(570, $report['totals']['actual_minutes']);
        $this->assertSame(300, $report['totals']['billable_minutes']);
        $this->assertSame(-30, $report['totals']['delta_minutes']);
        $this->assertSame(1, $report['totals']['no_plan_projects']);
    }

    // ── A14 · MVP-333: Standort-Dimension ───────────────────────────────────

    public function test_site_dimension_distributes_closed_visits_and_flags_unassigned(): void {
        $a = User::factory()->create(['organization_id' => $this->organization->id]);
        $b = User::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Werk Nord']);

        $g1 = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'label' => 'Werk Nord Tor 1',
            'center_lat' => '50.0',
            'center_lng' => '8.0',
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
        $g2 = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'site_id' => null,
            'label' => 'Ohne Standort',
            'center_lat' => '51.0',
            'center_lng' => '9.0',
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);

        $visit = function (User $user, CustomerGeofence $g, string $enteredAt, ?int $minutes, string $status): void {
            LocationVisit::create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'customer_geofence_id' => $g->id,
                'entered_at' => $enteredAt,
                'left_at' => $minutes !== null ? CarbonImmutable::parse($enteredAt)->addMinutes($minutes)->toDateTimeString() : null,
                'duration_min' => $minutes,
                'sample_count' => 3,
                'status' => $status,
                'materialized' => false,
            ]);
        };

        $visit($a, $g1, '2024-01-15 08:00:00', 90, LocationVisit::STATUS_CLOSED);
        $visit($b, $g1, '2024-01-15 09:00:00', 30, LocationVisit::STATUS_CLOSED);
        $visit($a, $g2, '2024-01-16 08:00:00', 45, LocationVisit::STATUS_CLOSED);
        $visit($a, $g1, '2024-01-16 10:00:00', null, LocationVisit::STATUS_OPEN); // offen → zählt nicht
        $visit($a, $g1, '2024-02-05 08:00:00', 60, LocationVisit::STATUS_CLOSED); // außerhalb

        $from = CarbonImmutable::create(2024, 1, 1) ?? CarbonImmutable::now();
        $to = CarbonImmutable::create(2024, 1, 31) ?? CarbonImmutable::now();
        $report = $this->builder->siteFor($from, $to);

        $this->assertCount(2, $report['rows']);

        // Handrechnung Werk Nord: 90 + 30 = 120 min, 2 Besuche, 2 Personen.
        $this->assertSame('Werk Nord', $report['rows'][0]['name']);
        $this->assertSame(120, $report['rows'][0]['actual_minutes']);
        $this->assertSame(2, $report['rows'][0]['visits']);
        $this->assertSame(2, $report['rows'][0]['users']);
        $this->assertSame($customer->name, $report['rows'][0]['customer']);

        // Geofence ohne Standort-Zuordnung als eigene Zeile am Ende.
        $this->assertNull($report['rows'][1]['site_id']);
        $this->assertSame(45, $report['rows'][1]['actual_minutes']);

        $this->assertSame(3, $report['totals']['visits']);
        $this->assertSame(165, $report['totals']['actual_minutes']);
        $this->assertSame(2, $report['totals']['users']); // distinct, nicht Zeilensumme
    }

    public function test_extended_dimensions_are_org_isolated(): void {
        // Fremde Organisation mit Schichten, Projektzeiten und Ortsbesuchen.
        $otherOrg = \App\Models\Organization::factory()->create();
        $foreignUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignType = ShiftType::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Fremdschicht']);
        ScheduledShift::factory()->published()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $foreignUser->id,
            'shift_type_id' => $foreignType->id,
            'date' => '2024-01-15',
        ]);
        $foreignProject = Project::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Fremdprojekt']);
        TimeEntry::factory()->create(['organization_id' => $otherOrg->id, 'user_id' => $foreignUser->id, 'project_id' => $foreignProject->id, 'date' => '2024-01-15', 'minutes' => 60, 'billable' => false]);

        $from = CarbonImmutable::create(2024, 1, 1) ?? CarbonImmutable::now();
        $to = CarbonImmutable::create(2024, 1, 31) ?? CarbonImmutable::now();

        // Gebundene Organisation (WithOrganization) sieht nichts davon.
        $this->assertSame([], $this->builder->shiftFor($from, $to)['rows']);
        $this->assertSame([], $this->builder->projectTimeFor($from, $to)['rows']);
        $this->assertSame([], $this->builder->siteFor($from, $to)['rows']);
    }
}
