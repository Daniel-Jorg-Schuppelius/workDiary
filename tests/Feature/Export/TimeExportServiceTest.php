<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, TimeExportEvent, User};
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\{TimeExportException, TimeExportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-019 — ApprovedTimeExporter Pipeline-Tests.
 */
class TimeExportServiceTest extends TestCase {
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

    public function test_prepare_fails_when_no_closures_exist(): void {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $this->expectException(TimeExportException::class);
        $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
    }

    public function test_prepare_fails_when_closure_not_approved(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);

        $this->actingAs($user);
        $closure = $this->closureService->getOrCreate($user, $this->year, $this->month);
        $this->closureService->submit($closure, $user);
        // bewusst NICHT approven

        $this->actingAs($admin);

        $this->expectException(TimeExportException::class);
        $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
    }

    public function test_build_writes_file_hashes_and_locks_closure(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);   // 8 h Tag 15.
        $this->seedAttendance($user, 7 * 60, 16); // 7 h Tag 16. => 15 h gesamt

        $closure = $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
        $this->assertSame(TimeExportStatus::Preparing, $export->status);

        $built = $this->service->build($export, $admin);

        $this->assertSame(TimeExportStatus::Ready, $built->status);
        $this->assertNotNull($built->payload_hash);
        $this->assertNotNull($built->file_path);
        $this->assertSame(1, $built->rows_count, 'Eine Zeile (work.normal) pro User.');
        Storage::disk('local')->assertExists((string) $built->file_path);

        // Totals: 15 h für work.normal
        $totals = $built->totals;
        $this->assertIsArray($totals);
        $this->assertArrayHasKey('work.normal', $totals);
        $this->assertEqualsWithDelta(15.0, (float) $totals['work.normal']['quantity'], 0.001);

        // MonthClosure ist jetzt locked
        $closure->refresh();
        $this->assertSame(MonthClosureStatus::Locked, $closure->status);

        // Hash reproduzierbar
        $expected = hash('sha256', (string) Storage::disk('local')->get((string) $built->file_path));
        $this->assertSame($expected, $built->payload_hash);

        // Audit-Events vorhanden
        $events = TimeExportEvent::query()->where('time_export_id', $built->id)->pluck('event')->all();
        $this->assertContains('export.preparing', $events);
        $this->assertContains('export.ready', $events);
    }

    public function test_re_export_supersedes_old_ready_export(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $closure = $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $first = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $first = $this->service->build($first, $admin);

        // Re-Open + erneut approven, damit prepare durchgeht.
        $closure->refresh();
        $this->closureService->reopen($closure, $admin, 'Korrektur fuer Re-Export Zwecke');
        $closure->refresh();
        $this->actingAs($user);
        $this->closureService->submit($closure, $user);
        $closure->refresh();
        $this->actingAs($admin);
        $this->closureService->approve($closure, $admin);

        $second = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $second = $this->service->build($second, $admin);

        $first->refresh();
        $this->assertSame(TimeExportStatus::Superseded, $first->status);
        $this->assertSame($second->id, $first->superseded_by_id);
        $this->assertSame(TimeExportStatus::Ready, $second->status);
    }

    public function test_mark_delivered_and_reject_transitions(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $delivered = $this->service->markDelivered($export, $admin, 'an DATEV gesendet');
        $this->assertSame(TimeExportStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame('an DATEV gesendet', $delivered->delivery_note);

        $rejected = $this->service->reject($delivered, $admin, 'Lohnbüro meldet Fehler');
        $this->assertSame(TimeExportStatus::Rejected, $rejected->status);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

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
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}
