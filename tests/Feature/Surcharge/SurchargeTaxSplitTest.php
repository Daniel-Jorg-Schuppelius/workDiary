<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeTaxSplitTest.php
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
 * Rang 36 (rescoped): wage-unabhängiger Prozent-Split der Zuschläge in
 * steuerfreie/-pflichtige Anteile (§ 3b-Grenzen als Konfiguration) — der
 * €-Grundlohn-Deckel bleibt Sache der externen Lohnrechnung.
 */
class SurchargeTaxSplitTest extends TestCase {
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

    public function test_split_produces_two_lines_with_percentage_parts(): void {
        // Nacht 40 %, steuerfrei bis 25 % → 25 % steuerfrei (2010) + 15 % steuerpflichtig (2011).
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '40.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'wage_type_code' => '2010',
            'tax_free_limit_pct' => '25.00',
            'taxable_wage_type_code' => '2011',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        // Lokal (Europe/Berlin, CET = UTC+1) 23:00–01:00 → 60 Nacht-Minuten
        // am 08.01. + 60 am 09.01.; in UTC gespeichert als 22:00–00:00.
        $this->seedAttendance($user, 8, 22, 0, 120);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin);

        // 1× work.normal + je Tag 2 Split-Zeilen = 5.
        $this->assertSame(5, $export->rows_count);

        $day = $export->lines()->where('wage_type', 'surcharge.night')
            ->whereDate('period_start', '2026-01-08')->orderBy('id')->get();
        $this->assertCount(2, $day);

        $free = $day->firstWhere('wage_type_code', '2010');
        $taxable = $day->firstWhere('wage_type_code', '2011');
        $this->assertNotNull($free);
        $this->assertNotNull($taxable);
        $this->assertSame('25.00', (string) $free->percentage);
        $this->assertSame('15.00', (string) $taxable->percentage);
        // Gleiche Stunden — die externe Lohnrechnung rechnet je Anteil.
        $this->assertSame((string) $free->quantity, (string) $taxable->quantity);
        $this->assertStringContainsString('steuerfrei', (string) $free->note);
        $this->assertStringContainsString('steuerpflichtig', (string) $taxable->note);
    }

    public function test_no_split_when_limit_missing_or_covering(): void {
        // Grenze >= Prozentsatz → kein Split (komplett steuerfrei).
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'tax_free_limit_pct' => '25.00',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8, 23, 0, 60);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin);

        $this->assertSame(1, $export->lines()->where('wage_type', 'surcharge.night')->count());
    }

    public function test_datev_render_contains_both_wage_types(): void {
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '40.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'wage_type_code' => '2010',
            'tax_free_limit_pct' => '25.00',
            'taxable_wage_type_code' => '2011',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser(['personnel_number' => 'P-1']);
        // Lokal 08.01. 23:00–24:00 (UTC: 22:00–23:00).
        $this->seedAttendance($user, 8, 22, 0, 60);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin, 'datev');

        $content = (string) Storage::disk('local')->get((string) $export->file_path);
        $this->assertStringContainsString('P-1;08.01.2026;2010;1,00;', $content);
        $this->assertStringContainsString('P-1;08.01.2026;2011;1,00;', $content);
    }

    public function test_admin_validation_requires_taxable_wage_type_for_split(): void {
        $admin = $this->makeAdmin();
        $admin->givePermissionTo([P::SurchargeRuleViewAny->value, P::SurchargeRuleManage->value]);
        $admin->unsetRelation('permissions');

        $this->actingAs($admin);

        $payload = [
            'code' => 'night',
            'label' => 'Nachtzuschlag',
            'kind' => 'night',
            'window_start' => '23:00',
            'window_end' => '06:00',
            'percentage' => '40.00',
            'wage_type_code' => '2010',
            'tax_free_limit_pct' => '25.00',
            'priority' => 0,
            'active' => 1,
        ];

        // Ohne Lohnart für den steuerpflichtigen Anteil → Validierungsfehler.
        $this->post(route('admin.surcharge-rules.store'), $payload)
            ->assertSessionHasErrors('taxable_wage_type_code');

        // Mit Lohnart klappt die Anlage.
        $this->post(route('admin.surcharge-rules.store'), $payload + ['taxable_wage_type_code' => '2011'])
            ->assertRedirect(route('admin.surcharge-rules.index'));

        $rule = SurchargeRule::query()->firstOrFail();
        $this->assertSame('25.00', (string) $rule->tax_free_limit_pct);
        $this->assertSame('2011', $rule->taxable_wage_type_code);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    private function buildExport(User $admin, string $profile = 'generic'): TimeExport {
        return $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, $profile, 'organization', actor: $admin),
            $admin,
        );
    }

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
