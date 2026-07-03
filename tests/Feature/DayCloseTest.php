<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\TimeApproval\{DayClosureStatus, DayCorrectionStatus};
use App\Enums\User\Permission as P;
use App\Models\{Attendance, AuditLog, DayClosure, DayCorrectionRequest, Organization, User};
use App\Services\TimeApproval\{DayCloseService, MonthClosureService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tagesabschluss (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md):
 * Seite (§2), Statusmaschine + Audits (§3/§6), ⛔-Block beim Abschluss (§4),
 * Korrektur-Workflow (§5) und Permissions/Tenant-Grenzen (§7).
 */
class DayCloseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Feste Zeit: Tagesabschluss-Logik hängt an heute/Zukunft (§2.6).
        $this->travelTo(CarbonImmutable::create(2026, 6, 10, 14, 0, 0));

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    // ── Seite (§2) ───────────────────────────────────────────────────────

    public function test_page_renders_all_sections_and_balance(): void {
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'started_at' => CarbonImmutable::parse('2026-06-10 08:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-10 12:00'),
        ]);

        $response = $this->actingAs($this->user)->get(route('day-close.show', ['date' => '2026-06-10']));

        $response->assertOk()
            ->assertSee(__('day-close.section.attendance'))
            ->assertSee(__('day-close.section.entries'))
            ->assertSee(__('day-close.section.issues'))
            ->assertSee(__('day-close.section.balance'))
            ->assertSee(__('day-close.field.target'))
            // Pausen sind in die Bilanz integriert (Ist + Soll).
            ->assertSee(__('day-close.field.recorded_break'))
            ->assertSee(__('day-close.field.required_break'))
            ->assertSee(__('day-close.field.net'))
            ->assertSee(__('day-close.field.booked'))
            ->assertSee(__('day-close.field.diff'))
            ->assertSee(__('day-close.field.day_balance'))
            ->assertSee(__('day-close.field.month_balance'))
            ->assertViewHas('aggregates', fn(array $agg): bool => $agg['gross'] === 240);
    }

    public function test_opening_own_day_creates_closure_with_opened_audit_once(): void {
        $this->actingAs($this->user)->get(route('day-close.show', ['date' => '2026-06-10']))->assertOk();
        $this->actingAs($this->user)->get(route('day-close.show', ['date' => '2026-06-10']))->assertOk();

        $closure = DayClosure::query()->where('user_id', $this->user->id)->whereDate('day', '2026-06-10')->sole();
        $this->assertSame(DayClosureStatus::Open, $closure->status);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.opened'));
    }

    // ── day.close (§2.6/§3/§4) ───────────────────────────────────────────

    public function test_close_succeeds_without_blocking_checks_and_writes_audit(): void {
        $response = $this->actingAs($this->user)->post(route('day-close.close'), ['date' => '2026-06-10']);

        $response->assertRedirect()->assertSessionHas('status');

        $closure = DayClosure::query()->where('user_id', $this->user->id)->whereDate('day', '2026-06-10')->sole();
        $this->assertSame(DayClosureStatus::Closed, $closure->status);
        $this->assertNotNull($closure->closed_at);
        $this->assertSame($this->user->id, $closure->closed_by_user_id);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.closed'));
    }

    public function test_close_is_blocked_by_open_attendance(): void {
        Attendance::factory()->open()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'started_at' => CarbonImmutable::parse('2026-06-10 08:00'),
        ]);

        $response = $this->actingAs($this->user)->post(route('day-close.close'), ['date' => '2026-06-10']);

        $response->assertRedirect()->assertSessionHas('error');

        $closure = DayClosure::query()->where('user_id', $this->user->id)->whereDate('day', '2026-06-10')->sole();
        $this->assertSame(DayClosureStatus::Open, $closure->status);
        $this->assertSame(0, $this->auditCount($closure, 'dayClose.closed'));
    }

    public function test_close_is_rejected_for_future_day(): void {
        $response = $this->actingAs($this->user)->post(route('day-close.close'), ['date' => '2026-06-11']);

        $response->assertRedirect()->assertSessionHas('error');

        $closure = DayClosure::query()->where('user_id', $this->user->id)->whereDate('day', '2026-06-11')->sole();
        $this->assertSame(DayClosureStatus::Open, $closure->status);
    }

    public function test_close_is_rejected_when_month_is_approved(): void {
        $this->lockMonthFor($this->user);

        $response = $this->actingAs($this->user)->post(route('day-close.close'), ['date' => '2026-06-10']);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame(
            DayClosureStatus::Open,
            DayClosure::query()->where('user_id', $this->user->id)->whereDate('day', '2026-06-10')->sole()->status,
        );
    }

    public function test_locked_month_is_shown_as_locked_status(): void {
        $this->lockMonthFor($this->user);

        $this->actingAs($this->user)->get(route('day-close.show', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertViewHas('effectiveStatus', DayClosureStatus::Locked)
            ->assertSee(__('day-close.hint.month_locked'));
    }

    // ── Korrektur-Workflow (§5/§6) ───────────────────────────────────────

    public function test_request_correction_requires_min_reason_length(): void {
        $this->closeDay($this->user, '2026-06-10');

        $response = $this->actingAs($this->user)
            ->from(route('day-close.show', ['date' => '2026-06-10']))
            ->post(route('day-close.request-correction'), [
                'date' => '2026-06-10',
                'reason' => 'zu kurz',
            ]);

        $response->assertRedirect()->assertSessionHasErrors('reason');
        $this->assertSame(0, DayCorrectionRequest::query()->count());
    }

    public function test_request_correction_creates_request_and_audit(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');

        $reason = 'Pause vergessen einzutragen, bitte korrigieren.';
        $response = $this->actingAs($this->user)->post(route('day-close.request-correction'), [
            'date' => '2026-06-10',
            'reason' => $reason,
        ]);

        $response->assertRedirect()->assertSessionHas('status');

        $closure->refresh();
        $this->assertSame(DayClosureStatus::Correction, $closure->status);

        $request = DayCorrectionRequest::query()->where('day_closure_id', $closure->id)->sole();
        $this->assertSame(DayCorrectionStatus::Pending, $request->status);
        $this->assertSame($reason, $request->reason);
        $this->assertSame($this->user->id, $request->requested_by_user_id);
        $this->assertSame((int) $this->organization->id, (int) $request->organization_id);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.correctionRequested'));
    }

    public function test_approve_correction_reopens_day_and_locks_attendance(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');
        $request = $this->requestCorrection($closure);
        $approver = $this->makeApprover();

        $response = $this->actingAs($approver)->post(route('day-close.correction.approve', $request));

        $response->assertRedirect()->assertSessionHas('status');

        $closure->refresh();
        $request->refresh();
        $this->assertSame(DayClosureStatus::Open, $closure->status);
        $this->assertTrue($closure->attendance_locked, 'Stempel bleiben nach Korrektur-Freigabe gesperrt (§5.5).');
        $this->assertSame(DayCorrectionStatus::Approved, $request->status);
        $this->assertSame($approver->id, $request->decided_by_user_id);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.correctionApproved'));

        // Stempel-Sperre greift in der Attendance-Bearbeitung (§5.5).
        $attendance = Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'started_at' => CarbonImmutable::parse('2026-06-10 08:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-10 12:00'),
        ]);
        $this->assertTrue(app(DayCloseService::class)->attendanceEditLocked($attendance));
    }

    public function test_reject_correction_keeps_day_closed(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');
        $request = $this->requestCorrection($closure);
        $approver = $this->makeApprover();

        $response = $this->actingAs($approver)->post(route('day-close.correction.reject', $request), [
            'note' => 'Tag ist korrekt erfasst.',
        ]);

        $response->assertRedirect()->assertSessionHas('status');

        $closure->refresh();
        $request->refresh();
        $this->assertSame(DayClosureStatus::Closed, $closure->status);
        $this->assertFalse($closure->attendance_locked);
        $this->assertSame(DayCorrectionStatus::Rejected, $request->status);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.correctionRejected'));
    }

    public function test_correction_buttons_are_offered_to_approvers_on_correction_status(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');
        $this->requestCorrection($closure);
        $approver = $this->makeApprover();

        $this->actingAs($approver)
            ->get(route('day-close.show', [
                'date' => '2026-06-10',
                'user' => Sqid::encode(User::class, $this->user->id),
            ]))
            ->assertOk()
            ->assertSee(__('day-close.action.approve'))
            ->assertSee(__('day-close.action.reject'));
    }

    // ── day.reopen (Admin, §2.6/§6) ──────────────────────────────────────

    public function test_reopen_requires_admin_permission(): void {
        $this->closeDay($this->user, '2026-06-10');

        $this->actingAs($this->user)->post(route('day-close.reopen'), [
            'date' => '2026-06-10',
            'reason' => 'Selbst-Reopen ist nicht vorgesehen!',
        ])->assertForbidden();
    }

    public function test_admin_reopen_requires_reason_and_writes_audit(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $userParam = Sqid::encode(User::class, $this->user->id);

        // Ohne ausreichende Begründung → Validation-Fehler, Status bleibt closed.
        $this->actingAs($admin)->post(route('day-close.reopen'), [
            'date' => '2026-06-10',
            'user' => $userParam,
            'reason' => 'kurz',
        ])->assertSessionHasErrors('reason');
        $this->assertSame(DayClosureStatus::Closed, $closure->refresh()->status);

        $this->actingAs($admin)->post(route('day-close.reopen'), [
            'date' => '2026-06-10',
            'user' => $userParam,
            'reason' => 'Nacherfassung nach Krankmeldung notwendig.',
        ])->assertRedirect()->assertSessionHas('status');

        $closure->refresh();
        $this->assertSame(DayClosureStatus::Open, $closure->status);
        $this->assertFalse($closure->attendance_locked, 'Admin-Reopen hebt die Stempel-Sperre auf.');
        $this->assertSame($admin->id, $closure->reopened_by_user_id);
        $this->assertSame(1, $this->auditCount($closure, 'dayClose.reopened'));
    }

    // ── Permissions (§7) + Tenant-Grenze ─────────────────────────────────

    public function test_user_without_day_close_permission_cannot_view(): void {
        $stranger = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('day-close.show', ['date' => '2026-06-10']))
            ->assertForbidden();
    }

    public function test_view_own_does_not_allow_foreign_days(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->closeDay($other, '2026-06-10');

        $this->actingAs($this->user)->get(route('day-close.show', [
            'date' => '2026-06-10',
            'user' => Sqid::encode(User::class, $other->id),
        ]))->assertForbidden();
    }

    public function test_view_team_permission_allows_foreign_days(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::DayCloseViewTeam->value);
        $viewer->unsetRelation('permissions');

        $this->actingAs($viewer)->get(route('day-close.show', [
            'date' => '2026-06-10',
            'user' => Sqid::encode(User::class, $other->id),
        ]))->assertOk()->assertSee(__('day-close.subtitle.other', ['name' => $other->name]));
    }

    public function test_view_organization_permission_allows_foreign_days(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::DayCloseViewOrganization->value);
        $viewer->unsetRelation('permissions');

        $this->actingAs($viewer)->get(route('day-close.show', [
            'date' => '2026-06-10',
            'user' => Sqid::encode(User::class, $other->id),
        ]))->assertOk();
    }

    public function test_cross_organization_user_is_not_found(): void {
        $orgB = Organization::factory()->create(['slug' => 'day-close-b']);
        $foreign = User::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($this->user)->get(route('day-close.show', [
            'date' => '2026-06-10',
            'user' => Sqid::encode(User::class, $foreign->id),
        ]))->assertNotFound();
    }

    public function test_correction_decision_is_denied_without_permission(): void {
        $closure = $this->closeDay($this->user, '2026-06-10');
        $request = $this->requestCorrection($closure);

        $this->actingAs($this->user)->post(route('day-close.correction.approve', $request))
            ->assertForbidden();
    }

    // ── intern ───────────────────────────────────────────────────────────

    private function closeDay(User $user, string $day): DayClosure {
        $service = app(DayCloseService::class);
        $closure = $service->getOrCreate($user, CarbonImmutable::parse($day));

        return $service->close($closure, $user);
    }

    private function requestCorrection(DayClosure $closure): DayCorrectionRequest {
        return app(DayCloseService::class)->requestCorrection(
            $closure,
            'Bitte Anwesenheit korrigieren, Stempel fehlt.',
            $closure->user,
        );
    }

    private function makeApprover(): User {
        $approver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $approver->givePermissionTo([
            P::DayCloseViewTeam->value,
            P::DayCloseApproveCorrection->value,
        ]);
        $approver->unsetRelation('permissions');

        return $approver;
    }

    /** Monat des Test-Datums über die Monatsfreigabe (MVP-016) sperren. */
    private function lockMonthFor(User $user): void {
        $service = app(MonthClosureService::class);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // MonthClosureService protokolliert den Actor aus Auth — ohne Login
        // schlägt der FK auf month_closure_events.actor_user_id fehl.
        $this->actingAs($user);
        $closure = $service->getOrCreate($user, 2026, 6);
        $service->approve($service->submit($closure, $user), $admin);
    }

    private function auditCount(DayClosure $closure, string $event): int {
        return AuditLog::query()
            ->where('auditable_type', DayClosure::class)
            ->where('auditable_id', $closure->id)
            ->where('event', $event)
            ->count();
    }
}
