<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\{ScheduledShiftStatus, ShiftExchangeStatus};
use App\Models\{ScheduledShift, ShiftExchange, User};
use App\Services\Schedule\{ShiftExchangeException, ShiftExchangeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftExchangeTest extends TestCase {
    use RefreshDatabase;

    public function test_employee_can_request_exchange_of_own_shift(): void {
        $user = User::factory()->user()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'date' => '2026-07-01',
        ]);

        $this->actingAs($user)
            ->post(route('schedule.exchanges.store'), [
                'scheduled_shift_id' => $shift->sqid,
                'reason' => 'Arzttermin',
            ])
            ->assertRedirect(route('schedule.exchanges.index'));

        $this->assertDatabaseHas('shift_exchanges', [
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $user->id,
            'status' => ShiftExchangeStatus::Requested->value,
        ]);
    }

    public function test_employee_cannot_request_exchange_for_foreign_shift(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->post(route('schedule.exchanges.store'), [
                'scheduled_shift_id' => $shift->sqid,
            ])
            ->assertRedirect();

        // Service rejects ownership: nothing created.
        $this->assertDatabaseMissing('shift_exchanges', ['scheduled_shift_id' => $shift->id]);
    }

    public function test_target_colleague_can_accept(): void {
        $requester = User::factory()->user()->create();
        $target = User::factory()->user()->create(['organization_id' => $requester->organization_id]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $requester->organization_id,
            'user_id' => $requester->id,
        ]);
        $exchange = ShiftExchange::factory()->create([
            'organization_id' => $requester->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target->id,
        ]);

        $this->actingAs($target)
            ->patch(route('schedule.exchanges.accept', $exchange))
            ->assertRedirect();

        $this->assertSame(ShiftExchangeStatus::Accepted, $exchange->fresh()->status);
    }

    public function test_team_lead_approval_reassigns_shift(): void {
        $lead = User::factory()->teamleitung()->create();
        $requester = User::factory()->user()->create(['organization_id' => $lead->organization_id]);
        $target = User::factory()->user()->create(['organization_id' => $lead->organization_id]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $requester->id,
            'date' => '2026-07-02',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $exchange = ShiftExchange::factory()->accepted()->create([
            'organization_id' => $lead->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target->id,
        ]);

        $this->actingAs($lead)
            ->patch(route('schedule.exchanges.approve', $exchange))
            ->assertRedirect();

        $this->assertSame(ShiftExchangeStatus::Approved, $exchange->fresh()->status);
        // Schicht wechselt den User.
        $this->assertSame($target->id, $shift->fresh()->user_id);
    }

    public function test_approval_blocked_when_compliance_violation(): void {
        $lead = User::factory()->teamleitung()->create();
        $requester = User::factory()->user()->create(['organization_id' => $lead->organization_id]);
        $target = User::factory()->user()->create(['organization_id' => $lead->organization_id]);

        // Ziel hat am selben Tag eine überschneidende Schicht → Overlap = ERROR.
        ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $target->id,
            'date' => '2026-07-03',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => ScheduledShiftStatus::Published->value,
        ]);

        $shift = ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $requester->id,
            'date' => '2026-07-03',
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
        ]);
        $exchange = ShiftExchange::factory()->accepted()->create([
            'organization_id' => $lead->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target->id,
        ]);

        $service = app(ShiftExchangeService::class);

        $this->expectException(ShiftExchangeException::class);
        $service->approve($exchange, $lead);
    }

    public function test_approval_override_bypasses_compliance(): void {
        $lead = User::factory()->teamleitung()->create();
        $requester = User::factory()->user()->create(['organization_id' => $lead->organization_id]);
        $target = User::factory()->user()->create(['organization_id' => $lead->organization_id]);

        ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $target->id,
            'date' => '2026-07-04',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => ScheduledShiftStatus::Published->value,
        ]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $requester->id,
            'date' => '2026-07-04',
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
        ]);
        $exchange = ShiftExchange::factory()->accepted()->create([
            'organization_id' => $lead->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target->id,
        ]);

        app(ShiftExchangeService::class)->approve($exchange, $lead, overrideCompliance: true);

        $this->assertSame(ShiftExchangeStatus::Approved, $exchange->fresh()->status);
        $this->assertSame($target->id, $shift->fresh()->user_id);
    }

    public function test_real_swap_reassigns_both_shifts(): void {
        $lead = User::factory()->teamleitung()->create();
        $a = User::factory()->user()->create(['organization_id' => $lead->organization_id]);
        $b = User::factory()->user()->create(['organization_id' => $lead->organization_id]);

        $shiftA = ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $a->id,
            'date' => '2026-07-05',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $shiftB = ScheduledShift::factory()->create([
            'organization_id' => $lead->organization_id,
            'user_id' => $b->id,
            'date' => '2026-07-06',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $exchange = ShiftExchange::factory()->accepted()->create([
            'organization_id' => $lead->organization_id,
            'scheduled_shift_id' => $shiftA->id,
            'requested_by_user_id' => $a->id,
            'target_user_id' => $b->id,
            'offered_shift_id' => $shiftB->id,
        ]);

        app(ShiftExchangeService::class)->approve($exchange, $lead);

        $this->assertSame($b->id, $shiftA->fresh()->user_id);
        $this->assertSame($a->id, $shiftB->fresh()->user_id);
    }

    public function test_regular_user_cannot_approve(): void {
        $requester = User::factory()->user()->create();
        $target = User::factory()->user()->create(['organization_id' => $requester->organization_id]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $requester->organization_id,
            'user_id' => $requester->id,
        ]);
        $exchange = ShiftExchange::factory()->accepted()->create([
            'organization_id' => $requester->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target->id,
        ]);

        $this->actingAs($target)
            ->patch(route('schedule.exchanges.approve', $exchange))
            ->assertForbidden();
    }

    public function test_requester_can_cancel_open_request(): void {
        $user = User::factory()->user()->create();
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);
        $exchange = ShiftExchange::factory()->create([
            'organization_id' => $user->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('schedule.exchanges.cancel', $exchange))
            ->assertRedirect();

        $this->assertSame(ShiftExchangeStatus::Cancelled, $exchange->fresh()->status);
    }
}
