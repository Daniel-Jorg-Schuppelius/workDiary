<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportSurchargeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Surcharge;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, TimeExport, User};
use App\Models\Surcharge\SurchargeRule;
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\TimeExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 005 — Zuschlagszeilen im TimeExport und DATEV-Profil-Format.
 *
 * Fixe Periode Januar 2026: 08./09.01. = Do/Fr (kein Wochenende).
 */
class TimeExportSurchargeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TimeExportService $service;

    private MonthClosureService $closureService;

    private int $year = 2026;

    private int $month = 1;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');

        $this->service = app(TimeExportService::class);
        $this->closureService = app(MonthClosureService::class);
    }

    public function test_export_contains_surcharge_lines_with_correct_minutes_and_wage_type(): void {
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        // Do 08.01. 22:00 → Fr 09.01. 06:00 = 480 min Anwesenheit,
        // davon Nacht: 60 min am 08.01. + 360 min am 09.01.
        $this->seedAttendance($user, 8, 22, 0, 480);

        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        // 1× work.normal + 2 Zuschlags-Tageszeilen
        $this->assertSame(3, $export->rows_count);

        $surchargeLines = $export->lines()->where('wage_type', 'surcharge.night')->orderBy('period_start')->get();
        $this->assertCount(2, $surchargeLines);

        $first = $surchargeLines[0];
        $this->assertSame('2026-01-08', $first->period_start->toDateString());
        $this->assertSame('2026-01-08', $first->period_end->toDateString());
        $this->assertSame(1.0, (float) $first->quantity);       // 60 min
        $this->assertSame('2010', $first->wage_type_code);
        $this->assertSame('25.00', (string) $first->percentage);
        $this->assertNotNull($first->surcharge_rule_id);

        $second = $surchargeLines[1];
        $this->assertSame('2026-01-09', $second->period_start->toDateString());
        $this->assertSame(6.0, (float) $second->quantity);      // 360 min

        // Totals enthalten die Zuschlags-Lohnart.
        $totals = $export->totals;
        $this->assertIsArray($totals);
        $this->assertArrayHasKey('surcharge.night', $totals);
        $this->assertEqualsWithDelta(7.0, (float) $totals['surcharge.night']['quantity'], 0.001);
    }

    /**
     * Whitebox 2026-07-10 (Z3): Wochentags-Zuschläge gelten für den LOKALEN
     * Kalendertag. Sa 10.01.2026 00:30–05:30 Europe/Berlin ist in UTC noch
     * Freitag (09.01. 23:30) — eine UTC-Rechnung verlöre den kompletten
     * Samstagszuschlag.
     */
    public function test_saturday_surcharge_uses_local_calendar_day(): void {
        SurchargeRule::factory()->saturday()->create([
            'organization_id' => $this->organization->id,
            'code' => 'saturday',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        // UTC: Fr 09.01. 23:30 + 300 min = Sa 10.01. 04:30 UTC
        // Lokal: Sa 10.01. 00:30–05:30 → 300 Zuschlagsminuten am Samstag.
        $this->seedAttendance($user, 9, 23, 30, 300);

        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $lines = $export->lines()->where('wage_type', 'surcharge.saturday')->get();
        $this->assertCount(1, $lines, 'Der Samstagszuschlag darf in UTC nicht verloren gehen.');
        $this->assertSame('2026-01-10', $lines[0]->period_start->toDateString());
        $this->assertSame(5.0, (float) $lines[0]->quantity); // 300 min
    }

    public function test_inactive_rule_produces_no_surcharge_lines(): void {
        SurchargeRule::factory()->night()->inactive()->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $this->assertSame(1, $export->rows_count);
        $this->assertSame(0, $export->lines()->where('wage_type', 'surcharge.night')->count());
    }

    public function test_datev_profile_renders_personnel_date_wage_type_hours(): void {
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser(['personnel_number' => 'P-4711']);
        $this->seedAttendance($user, 8, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        $this->assertInstanceOf(TimeExport::class, $export);
        $content = (string) Storage::disk('local')->get((string) $export->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content)));

        $this->assertSame('Personalnummer;Datum;Lohnart;Stunden;Kostenstelle', $lines[0]);

        // work.normal (Monatszeile, Default-Lohnart 1000, Monatsletzter) +
        // 2 Zuschlagszeilen (Lohnart 2010, Tagesdatum).
        $this->assertContains('P-4711;31.01.2026;1000;8,00;', $lines);
        $this->assertContains('P-4711;08.01.2026;2010;1,00;', $lines);
        $this->assertContains('P-4711;09.01.2026;2010;6,00;', $lines);
        $this->assertCount(4, $lines);
    }

    public function test_rules_of_other_organization_do_not_leak_into_export(): void {
        $orgB = \App\Models\Organization::factory()->create();
        SurchargeRule::factory()->night()->create([
            'organization_id' => $orgB->id,
            'code' => 'night',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $this->assertSame(1, $export->rows_count);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    private function seedAttendance(User $user, int $day, int $hour, int $minute, int $minutes): void {
        $date = CarbonImmutable::create($this->year, $this->month, $day) ?? CarbonImmutable::now();
        $start = $date->setTime($hour, $minute);
        Attendance::withoutEvents(function () use ($user, $date, $start, $minutes): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $start,
                'ended_at' => $start->addMinutes($minutes),
                'duration_minutes' => $minutes,
                'status' => AttendanceStatus::Closed,
            ]);
        });
    }

    private function approvedClosureFor(User $user, User $admin): MonthClosure {
        $this->actingAs($user);
        $closure = $this->closureService->getOrCreate($user, $this->year, $this->month);
        $closure = $this->closureService->submit($closure, $user);
        $this->actingAs($admin);

        return $this->closureService->approve($closure, $admin);
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeUser(array $attributes = []): User {
        /** @var User $user */
        $user = User::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
        $user->givePermissionTo([
            P::MonthViewOwn->value,
            P::MonthSubmitOwn->value,
        ]);
        $user->unsetRelation('permissions');

        return $user;
    }

    private function makeAdmin(): User {
        /** @var User $admin */
        $admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo([
            P::MonthViewOrganization->value,
            P::MonthApprove->value,
            P::MonthReject->value,
            P::MonthReopen->value,
            P::MonthLock->value,
            P::ExportTimeCreate->value,
            P::ExportTimeDeliver->value,
            P::ExportTimeDelete->value,
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}
