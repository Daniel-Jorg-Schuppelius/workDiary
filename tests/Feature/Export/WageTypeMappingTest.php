<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageTypeMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, Organization, User, WageTypeMapping};
use App\Models\Surcharge\SurchargeRule;
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\{TimeExportException, TimeExportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * A21 · MVP-019 — Lohnarten-Mapping: Modal-CRUD (org-isoliert, validiert)
 * und Auflösung im Export (Mapping > Regel-Code > Default) inklusive
 * Preflight bei fehlender Pflicht-Zuordnung.
 */
class WageTypeMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TimeExportService $service;
    private MonthClosureService $closureService;
    private int $year = 2024;
    private int $month = 1;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');

        $this->service = app(TimeExportService::class);
        $this->closureService = app(MonthClosureService::class);
    }

    // ── CRUD + Org-Isolation ───────────────────────────────────────────

    public function test_index_requires_permission(): void {
        $nobody = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($nobody)
            ->get(route('admin.wage-type-mappings.index'))
            ->assertForbidden();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->get(route('admin.wage-type-mappings.index'))
            ->assertOk()
            ->assertSee(__('wage_types.title.index'));
    }

    public function test_store_creates_mapping_for_current_organization(): void {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.wage-type-mappings.store'), [
                'profile' => 'datev',
                'wage_type' => 'work.normal',
                'external_code' => '2500',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));

        $this->assertDatabaseHas('wage_type_mappings', [
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'work.normal',
            'external_code' => '2500',
        ]);
    }

    public function test_store_rejects_duplicate_mapping_per_org_and_profile(): void {
        $admin = $this->makeAdmin();
        WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'work.normal',
            'external_code' => '2500',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.wage-type-mappings.index'))
            ->post(route('admin.wage-type-mappings.store'), [
                'profile' => 'datev',
                'wage_type' => 'work.normal',
                'external_code' => '2600',
            ])
            ->assertSessionHasErrors('wage_type');

        // Gleiches Mapping in einem ANDEREN Profil bleibt erlaubt.
        $this->actingAs($admin)
            ->post(route('admin.wage-type-mappings.store'), [
                'profile' => 'lexware',
                'wage_type' => 'work.normal',
                'external_code' => '2600',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_external_code_is_validated_against_profile_pattern(): void {
        $admin = $this->makeAdmin();

        // DATEV: numerisch, max. 4 Stellen — Buchstaben und 5 Stellen scheitern.
        foreach (['ABC', '12345'] as $bad) {
            $this->actingAs($admin)
                ->from(route('admin.wage-type-mappings.index'))
                ->post(route('admin.wage-type-mappings.store'), [
                    'profile' => 'datev',
                    'wage_type' => 'work.normal',
                    'external_code' => $bad,
                ])
                ->assertSessionHasErrors('external_code');
        }
        $this->assertDatabaseCount('wage_type_mappings', 0);

        // Generic: alphanumerische Codes sind zulässig.
        $this->actingAs($admin)
            ->post(route('admin.wage-type-mappings.store'), [
                'profile' => 'generic',
                'wage_type' => 'work.normal',
                'external_code' => 'LA-100',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_mapping_of_other_organization_is_not_reachable(): void {
        $mapping = WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'work.normal',
            'external_code' => '2500',
        ]);

        $orgB = Organization::factory()->create();
        /** @var User $adminB */
        $adminB = User::factory()->create(['organization_id' => $orgB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $adminB->givePermissionTo([P::WageTypeMappingViewAny->value, P::WageTypeMappingManage->value]);
        $adminB->unsetRelation('permissions');

        // Route-Binding läuft über den OrganizationScope → fremde Sqid = 404.
        $this->actingAs($adminB)
            ->get(route('admin.wage-type-mappings.edit', $mapping))
            ->assertNotFound();
        $this->actingAs($adminB)
            ->put(route('admin.wage-type-mappings.update', $mapping), [
                'profile' => 'datev',
                'wage_type' => 'work.normal',
                'external_code' => '9999',
            ])
            ->assertNotFound();

        $this->assertSame('2500', $mapping->refresh()->external_code);
    }

    public function test_update_and_destroy_roundtrip(): void {
        $admin = $this->makeAdmin();
        $mapping = WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'work.normal',
            'external_code' => '2500',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.wage-type-mappings.update', $mapping), [
                'profile' => 'datev',
                'wage_type' => 'work.normal',
                'external_code' => '2600',
            ])
            ->assertRedirect(route('admin.wage-type-mappings.index'));
        $this->assertSame('2600', $mapping->refresh()->external_code);

        $this->actingAs($admin)
            ->delete(route('admin.wage-type-mappings.destroy', $mapping))
            ->assertRedirect(route('admin.wage-type-mappings.index'));
        $this->assertDatabaseMissing('wage_type_mappings', ['id' => $mapping->id]);
    }

    // ── Export nutzt Mapping / Fallback / Preflight ────────────────────

    public function test_datev_export_uses_mapping_for_normal_hours(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();
        $this->seedAttendance($user, 8 * 60);
        $this->seedAttendance($user, 7 * 60, 16);
        $this->approvedClosureFor($user, $admin);

        WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'work.normal',
            'external_code' => '2500',
        ]);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        // Golden Line: gemappte Lohnart 2500 statt Default 1000.
        $this->assertSame('4711;31.01.2024;2500;15,00;', $lines[1]);
        $this->assertCount(2, $lines);
    }

    public function test_lexware_export_uses_mapping_for_normal_hours(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();
        $this->seedAttendance($user, 8 * 60);
        $this->seedAttendance($user, 7 * 60, 16);
        $this->approvedClosureFor($user, $admin);

        WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'lexware',
            'wage_type' => 'work.normal',
            'external_code' => '3100',
        ]);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'lexware', 'organization', actor: $admin),
            $admin,
        );

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        $this->assertSame('2024;01;4711;3100;15,00;', $lines[1]);
    }

    public function test_datev_export_falls_back_to_default_without_mapping(): void {
        // Charakterisierung des Bestands: ohne Mapping bleibt die
        // konfigurierte Default-Lohnart 1000 für work.normal wirksam.
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();
        $this->seedAttendance($user, 8 * 60);
        $this->seedAttendance($user, 7 * 60, 16);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        $this->assertSame('4711;31.01.2024;1000;15,00;', $lines[1]);
    }

    public function test_mapping_wins_over_surcharge_rule_code(): void {
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night', // Factory-Regel-Code 2010
        ]);
        WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'surcharge.night',
            'external_code' => '8100',
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();
        // Nacht-Fixture wie TimeExportSurchargeTest: 15.01. 22:00 + 480 min.
        $this->seedAttendanceAt($user, 15, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        $content = (string) Storage::disk('local')->get((string) $built->file_path);

        // Zuschlagszeilen tragen die gemappte 8100, nicht den Regel-Code 2010.
        $this->assertStringContainsString(';8100;', $content);
        $this->assertStringNotContainsString(';2010;', $content);
    }

    public function test_missing_mapping_for_codeless_surcharge_fails_preflight(): void {
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'wage_type_code' => null, // weder Regel-Code noch Mapping
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendanceAt($user, 15, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin);

        try {
            $this->service->build($export, $admin);
            $this->fail('Preflight hätte den Export abbrechen müssen.');
        } catch (TimeExportException $e) {
            $this->assertStringContainsString('surcharge.night', $e->getMessage());
        }

        // Rollback: Export bleibt preparing, keine Datei, Freigabe nicht gesperrt.
        $export->refresh();
        $this->assertSame(TimeExportStatus::Preparing, $export->status);
        $this->assertNull($export->file_path);

        // Mit gepflegtem Mapping läuft derselbe Export durch.
        WageTypeMapping::query()->create([
            'organization_id' => $this->organization->id,
            'profile' => 'datev',
            'wage_type' => 'surcharge.night',
            'external_code' => '8100',
        ]);
        $built = $this->service->build($export, $admin);
        $this->assertSame(TimeExportStatus::Ready, $built->status);
        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $this->assertStringContainsString(';8100;', $content);
    }

    public function test_generic_profile_needs_no_mapping_for_codeless_surcharge(): void {
        // Charakterisierung: generic exportiert interne wage_type-Schlüssel,
        // der Preflight greift dort bewusst nicht.
        SurchargeRule::factory()->night('23:00:00', '06:00:00', '25.00')->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'wage_type_code' => null,
        ]);

        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendanceAt($user, 15, 22, 0, 480);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $this->assertSame(TimeExportStatus::Ready, $built->status);
    }

    // ── Helfer (Muster TimeExportServiceTest) ──────────────────────────

    private function seedAttendance(User $user, int $minutes, int $day = 15): void {
        $date = CarbonImmutable::create($this->year, $this->month, $day) ?? CarbonImmutable::now();
        Attendance::withoutEvents(function () use ($user, $minutes, $date): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $date->setTime(8, 0),
                'ended_at' => $date->setTime(8, 0)->addMinutes($minutes),
                'duration_minutes' => $minutes,
                'status' => AttendanceStatus::Closed,
            ]);
        });
    }

    private function seedAttendanceAt(User $user, int $day, int $hour, int $minute, int $minutes): void {
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

    private function makeUser(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
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
            P::WageTypeMappingViewAny->value,
            P::WageTypeMappingManage->value,
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}
