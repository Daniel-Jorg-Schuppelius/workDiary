<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Filename : TimeExportControllerTest.php
 * License  : AGPL-3.0-or-later
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, TimeExport, User};
use App\Services\TimeApproval\MonthClosureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-019 — HTTP-Endpunkte für ApprovedTimeExporter.
 */
class TimeExportControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private int $year = 2024;
    private int $month = 2;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
    }

    public function test_index_is_accessible_for_admin_and_shows_empty_state(): void {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('exports.index'))
            ->assertOk()
            ->assertSee(__('Zeit-Exporte'));
    }

    public function test_index_forbidden_for_user_without_permission(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('exports.index'))
            ->assertForbidden();
    }

    public function test_store_creates_export_for_approved_month(): void {
        $admin = $this->makeAdmin();
        $employee = $this->makeUser();

        $this->seedAttendance($employee, 8 * 60, 5);
        $this->seedAttendance($employee, 8 * 60, 6);

        $this->approveClosure($employee, $admin);

        $response = $this->actingAs($admin)->post(route('exports.store'), [
            'year' => $this->year,
            'month' => $this->month,
            'profile' => 'generic',
            'scope' => 'organization',
        ]);

        $response->assertRedirect();
        $export = TimeExport::query()->latest('id')->first();
        $this->assertNotNull($export);
        $this->assertSame(TimeExportStatus::Ready, $export->status);
        $this->assertSame(1, $export->rows_count);
    }

    public function test_store_redirects_back_with_error_when_no_closure(): void {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->from(route('exports.create'))
            ->post(route('exports.store'), [
                'year' => $this->year,
                'month' => $this->month,
                'profile' => 'generic',
                'scope' => 'organization',
            ]);

        $response->assertRedirect(route('exports.create'));
        $response->assertSessionHas('error');
        $this->assertSame(0, TimeExport::query()->count());
    }

    public function test_download_returns_file_for_ready_export(): void {
        $admin = $this->makeAdmin();
        $employee = $this->makeUser();
        $this->seedAttendance($employee, 8 * 60, 5);
        $this->approveClosure($employee, $admin);

        $this->actingAs($admin)->post(route('exports.store'), [
            'year' => $this->year,
            'month' => $this->month,
            'profile' => 'generic',
            'scope' => 'organization',
        ])->assertRedirect();

        $export = TimeExport::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('exports.download', $export))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=generic-2024-02.csv');
    }

    public function test_deliver_marks_export_delivered(): void {
        $admin = $this->makeAdmin();
        $employee = $this->makeUser();
        $this->seedAttendance($employee, 8 * 60, 5);
        $this->approveClosure($employee, $admin);

        $this->actingAs($admin)->post(route('exports.store'), [
            'year' => $this->year,
            'month' => $this->month,
            'profile' => 'generic',
            'scope' => 'organization',
        ]);

        $export = TimeExport::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('exports.deliver', $export), ['note' => 'an Lohnbuero gesendet'])
            ->assertRedirect();

        $this->assertSame(TimeExportStatus::Delivered, $export->fresh()?->status);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    private function seedAttendance(User $user, int $minutes, int $day): void {
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

    private function approveClosure(User $user, User $admin): MonthClosure {
        $closureService = app(MonthClosureService::class);
        $this->actingAs($user);
        $closure = $closureService->getOrCreate($user, $this->year, $this->month);
        $closure = $closureService->submit($closure, $user);
        $this->actingAs($admin);

        return $closureService->approve($closure, $admin);
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
}
