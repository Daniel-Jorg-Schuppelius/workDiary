<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeSwapTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\{ScheduledShiftStatus, ShiftExchangeStatus};
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Models\Schedule\{ScheduledShift, ShiftExchange};
use App\Services\Schedule\{ShiftExchangeException, ShiftExchangeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-1553: Schicht gegen Schicht im Antragsdialog, Übersteuerung mit Begründung im Freigabedialog. */
class ShiftExchangeSwapTest extends TestCase {
    use RefreshDatabase;

    private User $lead;

    private User $requester;

    private User $target;

    protected function setUp(): void {
        parent::setUp();
        $this->lead = User::factory()->teamleitung()->create();
        $this->requester = User::factory()->user()->create(['organization_id' => $this->lead->organization_id]);
        $this->target = User::factory()->user()->create(['organization_id' => $this->lead->organization_id]);
    }

    public function test_dialog_offers_colleague_shifts_and_request_stores_the_swap(): void {
        $day = now()->addDays(5)->toDateString();
        $mine = $this->shift($this->requester, $day, '06:00:00', '14:00:00');
        $theirs = $this->shift($this->target, $day, '14:00:00', '22:00:00');

        $this->actingAs($this->requester)->get(route('schedule.exchanges.create') . '?dialog=1')->assertOk()
            ->assertSee('name="offered_shift_id"', false)
            ->assertSee('value="' . $theirs->sqid . '"', false);

        // Ohne Wunschperson legt die Gegenschicht die Tauschperson fest.
        $this->actingAs($this->requester)->post(route('schedule.exchanges.store'), [
            'scheduled_shift_id' => $mine->sqid,
            'offered_shift_id' => $theirs->sqid,
        ])->assertRedirect(route('schedule.exchanges.index'));

        $exchange = ShiftExchange::query()->firstOrFail();
        $this->assertSame($theirs->id, (int) $exchange->offered_shift_id);
        $this->assertSame($this->target->id, (int) $exchange->target_user_id);

        $this->actingAs($this->requester)->get(route('schedule.exchanges.index'))->assertOk()
            ->assertSee(__('schedule.exchange.swap'))
            ->assertSee(__('schedule.exchange.offered_for'));
    }

    public function test_offered_shift_must_belong_to_the_chosen_person(): void {
        $day = now()->addDays(5)->toDateString();
        $mine = $this->shift($this->requester, $day, '06:00:00', '14:00:00');
        $third = User::factory()->user()->create(['organization_id' => $this->lead->organization_id]);
        $foreign = $this->shift($third, $day, '14:00:00', '22:00:00');

        $this->expectException(ShiftExchangeException::class);
        app(ShiftExchangeService::class)->request($mine, $this->requester, $this->target, $foreign);
    }

    public function test_same_day_swap_is_not_blocked_by_the_shift_given_away(): void {
        $day = now()->addDays(5)->toDateString();
        $mine = $this->shift($this->requester, $day, '06:00:00', '14:00:00');
        $theirs = $this->shift($this->target, $day, '12:00:00', '20:00:00');
        $exchange = $this->exchange($mine, $theirs);

        app(ShiftExchangeService::class)->approve($exchange, $this->lead);

        $this->assertSame($this->target->id, (int) $mine->fresh()?->user_id);
        $this->assertSame($this->requester->id, (int) $theirs->fresh()?->user_id);
        $this->assertSame(0, AuditLog::query()->where('event', 'shift_exchange.compliance_overridden')->count());
    }

    public function test_swap_fails_when_the_offered_shift_changed_hands(): void {
        $day = now()->addDays(5)->toDateString();
        $mine = $this->shift($this->requester, $day, '06:00:00', '14:00:00');
        $theirs = $this->shift($this->target, now()->addDays(6)->toDateString(), '06:00:00', '14:00:00');
        $exchange = $this->exchange($mine, $theirs);
        $theirs->update(['user_id' => $this->lead->id]);

        $this->expectException(ShiftExchangeException::class);
        app(ShiftExchangeService::class)->approve($exchange->fresh(), $this->lead);
    }

    public function test_violation_leads_to_the_override_dialog_and_needs_a_reason(): void {
        $day = now()->addDays(5)->toDateString();
        $this->shift($this->target, $day, '08:00:00', '16:00:00');
        $mine = $this->shift($this->requester, $day, '10:00:00', '14:00:00');
        $exchange = $this->exchange($mine, null);

        $this->actingAs($this->lead)->get(route('schedule.exchanges.index'))->assertOk()
            ->assertSee(__('schedule.exchange.violation_badge'))
            ->assertSee(route('schedule.exchanges.approve-form', $exchange), false);
        $this->actingAs($this->lead)->get(route('schedule.exchanges.approve-form', $exchange) . '?dialog=1')->assertOk()
            ->assertSee('name="override_compliance"', false)
            ->assertSee('name="override_reason"', false);

        // Ohne Übersteuerung und ohne Begründung bleibt der Antrag offen.
        $this->actingAs($this->lead)->patch(route('schedule.exchanges.approve', $exchange))->assertSessionHas('error');
        $this->actingAs($this->lead)->patch(route('schedule.exchanges.approve', $exchange), ['override_compliance' => '1', 'override_reason' => 'kurz'])
            ->assertSessionHas('error');
        $this->assertSame(ShiftExchangeStatus::Accepted, $exchange->fresh()?->status);

        $this->actingAs($this->lead)->patch(route('schedule.exchanges.approve', $exchange), [
            'override_compliance' => '1',
            'override_reason' => 'Notbesetzung, mit beiden Personen abgestimmt.',
        ])->assertSessionHas('success');

        $this->assertSame(ShiftExchangeStatus::Approved, $exchange->fresh()?->status);
        $log = AuditLog::query()->where('event', 'shift_exchange.compliance_overridden')->firstOrFail();
        $this->assertSame('Notbesetzung, mit beiden Personen abgestimmt.', $log->changes['reason'] ?? null);
        $this->assertNotEmpty($log->changes['violations'] ?? []);
    }

    public function test_override_dialog_is_reserved_for_deciders(): void {
        $mine = $this->shift($this->requester, now()->addDays(5)->toDateString(), '10:00:00', '14:00:00');
        $exchange = $this->exchange($mine, null);

        $this->actingAs($this->target)->get(route('schedule.exchanges.approve-form', $exchange))->assertForbidden();
    }

    private function shift(User $user, string $date, string $from, string $to): ScheduledShift {
        return ScheduledShift::factory()->create([
            'organization_id' => $this->lead->organization_id,
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $from,
            'end_time' => $to,
            'status' => ScheduledShiftStatus::Published->value,
        ]);
    }

    private function exchange(ScheduledShift $shift, ?ScheduledShift $offered): ShiftExchange {
        return ShiftExchange::factory()->accepted()->create([
            'organization_id' => $this->lead->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $this->requester->id,
            'target_user_id' => $this->target->id,
            'offered_shift_id' => $offered?->id,
        ]);
    }
}
