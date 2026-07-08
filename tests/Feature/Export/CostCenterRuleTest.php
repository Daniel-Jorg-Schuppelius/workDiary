<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, CostCenterRule, MonthClosure, Team, TimeExport, User};
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\TimeExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 35 (rescoped): Kostenstellen-Regeln (Benutzer > Team > Org-Default)
 * im Zeitexport, LODAS-Spalte, Zeilen-Override im Prüf-UI und Modal-CRUD.
 */
class CostCenterRuleTest extends TestCase {
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

    public function test_user_rule_beats_team_rule_beats_default(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($user->id);

        CostCenterRule::query()->create(['organization_id' => $this->organization->id, 'cost_center' => 'ORG-1', 'priority' => 0]);
        CostCenterRule::query()->create(['organization_id' => $this->organization->id, 'team_id' => $team->id, 'cost_center' => 'TEAM-1', 'priority' => 5]);
        CostCenterRule::query()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'cost_center' => 'USER-1', 'priority' => 0]);

        $this->seedAttendance($user, 8, 9, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin);

        // Benutzer-Regel gewinnt trotz höherer Team-Priorität.
        $this->assertSame('USER-1', $export->lines()->where('wage_type', 'work.normal')->value('cost_center'));

        // Ohne Benutzer-Regel greift die Team-Regel, danach der Default.
        CostCenterRule::query()->whereNotNull('user_id')->delete();
        $export2 = $this->buildExport($admin);
        $this->assertSame('TEAM-1', $export2->lines()->where('wage_type', 'work.normal')->value('cost_center'));

        CostCenterRule::query()->whereNotNull('team_id')->delete();
        $export3 = $this->buildExport($admin);
        $this->assertSame('ORG-1', $export3->lines()->where('wage_type', 'work.normal')->value('cost_center'));
    }

    public function test_datev_profile_renders_cost_center_column(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(['personnel_number' => 'P-4711']);
        CostCenterRule::query()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'cost_center' => '4200', 'priority' => 0]);

        $this->seedAttendance($user, 8, 9, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin, 'datev');

        $content = (string) Storage::disk('local')->get((string) $export->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content)));

        $this->assertSame('Personalnummer;Datum;Lohnart;Stunden;Kostenstelle', $lines[0]);
        $this->assertContains('P-4711;31.01.2026;1000;8,00;4200', $lines);
    }

    public function test_line_override_rerenders_file_only_while_ready(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(['personnel_number' => 'P-4711']);

        $this->seedAttendance($user, 8, 9, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->buildExport($admin, 'datev');
        $line = $export->lines()->firstOrFail();
        $oldHash = $export->payload_hash;

        $response = $this->patch(route('exports.lines.update', [$export, $line]), ['cost_center' => '9900']);
        $response->assertRedirect();

        $export->refresh();
        $this->assertSame('9900', $line->refresh()->cost_center);
        $this->assertNotSame($oldHash, $export->payload_hash, 'Override muss die Datei neu rendern.');

        $content = (string) Storage::disk('local')->get((string) $export->file_path);
        $this->assertStringContainsString('P-4711;31.01.2026;1000;8,00;9900', $content);

        // Nach Auslieferung ist die Zeile nicht mehr korrigierbar
        // (Policy deliver verlangt Status ready → 403).
        $this->service->markDelivered($export, $admin);
        $this->patch(route('exports.lines.update', [$export, $line]), ['cost_center' => '0000'])
            ->assertForbidden();
        $this->assertSame('9900', $line->refresh()->cost_center);
    }

    public function test_admin_crud_is_org_scoped_and_requires_permission(): void {
        $admin = $this->makeAdmin();
        $admin->givePermissionTo([P::CostCenterRuleViewAny->value, P::CostCenterRuleManage->value]);
        $admin->unsetRelation('permissions');

        $plain = $this->makeUser();

        $this->actingAs($plain);
        $this->get(route('admin.cost-center-rules.index'))->assertForbidden();

        $this->actingAs($admin);
        $this->get(route('admin.cost-center-rules.index'))->assertOk();

        $this->post(route('admin.cost-center-rules.store'), [
            'source' => 'default',
            'cost_center' => '1000',
            'priority' => 0,
        ])->assertRedirect(route('admin.cost-center-rules.index'));

        $rule = CostCenterRule::query()->firstOrFail();
        $this->assertSame((int) $this->organization->id, (int) $rule->organization_id);
        $this->assertNull($rule->user_id);

        // Quelle Benutzer verlangt eine Auswahl.
        $this->post(route('admin.cost-center-rules.store'), [
            'source' => 'user',
            'cost_center' => '2000',
            'priority' => 0,
        ])->assertSessionHasErrors('user_id');

        // Fremde Org sieht die Regel nicht.
        $orgB = \App\Models\Organization::factory()->create();
        $foreign = CostCenterRule::query()->create(['organization_id' => $orgB->id, 'cost_center' => 'B-1', 'priority' => 0]);
        $this->get(route('admin.cost-center-rules.edit', $foreign))->assertNotFound();
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
