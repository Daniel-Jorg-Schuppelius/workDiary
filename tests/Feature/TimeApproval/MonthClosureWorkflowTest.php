<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosureEvent, User};
use App\Services\TimeApproval\{MonthClosureService, MonthClosureWorkflowException};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class MonthClosureWorkflowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private MonthClosureService $service;
    private int $year = 2024;
    private int $month = 1;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->service = app(MonthClosureService::class);
    }

    public function test_get_or_create_is_idempotent_and_logs_draft_started_once(): void {
        $user = $this->makeUser();

        $a = $this->service->getOrCreate($user, $this->year, $this->month);
        $b = $this->service->getOrCreate($user, $this->year, $this->month);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(MonthClosureStatus::Draft, $a->status);
        $this->assertSame(1, MonthClosureEvent::query()->where('month_closure_id', $a->id)->count());
    }

    public function test_submit_blocked_when_open_attendance_exists(): void {
        $user = $this->makeUser();
        $date = CarbonImmutable::create($this->year, $this->month, 15) ?? CarbonImmutable::now();
        Attendance::factory()
            ->open()
            ->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $date->setTime(8, 0),
            ]);
        $closure = $this->service->getOrCreate($user, $this->year, $this->month);

        $this->expectException(MonthClosureWorkflowException::class);
        $this->service->submit($closure, $user);
    }

    public function test_full_workflow_submit_approve_lock_persists_snapshot(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $closure = $this->service->getOrCreate($user, $this->year, $this->month);

        $submitted = $this->service->submit($closure, $user);
        $this->assertSame(MonthClosureStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->totals, 'Totals werden beim Submit eingefroren.');

        $approved = $this->service->approve($submitted, $admin);
        $this->assertSame(MonthClosureStatus::Approved, $approved->status);
        $this->assertSame($admin->id, $approved->decided_by_user_id);

        $locked = $this->service->lock($approved, $admin);
        $this->assertSame(MonthClosureStatus::Locked, $locked->status);
        $this->assertNotNull($locked->locked_at);

        $events = MonthClosureEvent::query()
            ->where('month_closure_id', $closure->id)
            ->pluck('event')
            ->all();
        $this->assertSame(
            ['month.draftStarted', 'month.submitted', 'month.approved', 'month.locked'],
            $events,
        );
    }

    public function test_reject_requires_reason_of_min_length(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $closure = $this->service->submit($this->service->getOrCreate($user, $this->year, $this->month), $user);

        try {
            $this->service->reject($closure, 'zu kurz', $admin);
            $this->fail('Erwartete Exception wegen zu kurzer Begründung.');
        } catch (MonthClosureWorkflowException $e) {
            $this->assertSame('reasonTooShort', $e->reasonCode);
        }

        $longReason = str_repeat('A', MonthClosureService::REASON_MIN_LENGTH);
        $rejected = $this->service->reject($closure, $longReason, $admin);
        $this->assertSame(MonthClosureStatus::Rejected, $rejected->status);
        $this->assertSame($longReason, $rejected->decision_note);
    }

    public function test_self_reopen_from_rejected_to_draft_without_reason(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $closure = $this->service->submit($this->service->getOrCreate($user, $this->year, $this->month), $user);
        $closure = $this->service->reject($closure, str_repeat('B', 30), $admin);

        $reopened = $this->service->reopen($closure, $user);
        $this->assertSame(MonthClosureStatus::Draft, $reopened->status);
        $this->assertNull($reopened->decided_at);
        $this->assertDatabaseHas('month_closure_events', [
            'month_closure_id' => $closure->id,
            'event' => 'month.reopenedBySelf',
        ]);
    }

    public function test_admin_reopen_from_approved_requires_reason(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $closure = $this->service->approve(
            $this->service->submit($this->service->getOrCreate($user, $this->year, $this->month), $user),
            $admin,
        );

        try {
            $this->service->reopen($closure, $admin, 'kurz');
            $this->fail('Erwartete Exception wegen zu kurzer Begründung beim Admin-Reopen.');
        } catch (MonthClosureWorkflowException $e) {
            $this->assertSame('reasonTooShort', $e->reasonCode);
        }

        $reopened = $this->service->reopen($closure, $admin, str_repeat('C', 25));
        $this->assertSame(MonthClosureStatus::Reopened, $reopened->status);
    }

    public function test_illegal_transition_throws(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $closure = $this->service->getOrCreate($user, $this->year, $this->month);

        try {
            $this->service->approve($closure, $admin);
            $this->fail('Erwartete Exception bei approve(draft).');
        } catch (MonthClosureWorkflowException $e) {
            $this->assertSame('illegalTransition', $e->reasonCode);
        }
    }

    public function test_is_period_locked_for_user_reflects_submitted_approved_locked(): void {
        $user = $this->makeUser();
        $admin = $this->makeAdminUser();
        $day = CarbonImmutable::create($this->year, $this->month, 10) ?? CarbonImmutable::now();

        $this->assertFalse($this->service->isPeriodLockedForUser($user, $day), 'Ohne Closure ist nichts gesperrt.');

        $closure = $this->service->getOrCreate($user, $this->year, $this->month);
        $this->assertFalse($this->service->isPeriodLockedForUser($user, $day), 'Draft ist nicht gesperrt.');

        $closure = $this->service->submit($closure, $user);
        $this->assertTrue($this->service->isPeriodLockedForUser($user, $day), 'Submitted ist gesperrt.');

        $closure = $this->service->approve($closure, $admin);
        $this->assertTrue($this->service->isPeriodLockedForUser($user, $day), 'Approved ist gesperrt.');

        $this->service->lock($closure, $admin);
        $this->assertTrue($this->service->isPeriodLockedForUser($user, $day), 'Locked ist gesperrt.');
    }

    private function makeUser(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->givePermissionTo([
            P::MonthViewOwn->value,
            P::MonthSubmitOwn->value,
        ]);
        $user->unsetRelation('permissions');
        $this->actingAs($user);

        return $user;
    }

    private function makeAdminUser(): User {
        /** @var User $admin */
        $admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo([
            P::MonthViewOrganization->value,
            P::MonthApprove->value,
            P::MonthReject->value,
            P::MonthReopen->value,
            P::MonthLock->value,
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}
