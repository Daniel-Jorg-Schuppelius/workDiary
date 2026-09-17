<?php

/*
 * Filename     : TerminalPinTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Terminal;

use App\Models\{Attendance, AttendanceTerminal, User, UserTerminalPin};
use App\Services\Attendance\TerminalPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Terminal-PIN (MVP-803, Vollscan-Entscheid P6-18): Personalnummer + PIN als
 * Ersatz für einen vergessenen Ausweis, mit Sperre nach Fehlversuchen.
 */
final class TerminalPinTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $token;

    private User $admin;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        [, $this->token] = AttendanceTerminal::issue($this->organization->id, 'Werkstatt');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->employee = User::factory()->user()->create(['organization_id' => $this->organization->id, 'personnel_number' => '4711']);
    }

    public function test_admin_sets_a_pin_that_is_stored_only_as_hash(): void {
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), [
            'user' => $this->employee->sqid,
            'pin' => '2468',
            'pin_confirmation' => '2468',
        ])->assertRedirect()->assertSessionHas('success');

        $stored = (string) DB::table('user_terminal_pins')->value('pin_hash');
        $this->assertNotSame('2468', $stored);
        $this->assertStringNotContainsString('2468', $stored);
        $this->assertSame(1, UserTerminalPin::query()->sole()->auditLogs()->where('event', 'terminal.pin_set')->count());

        $this->actingAs($this->admin)->get(route('admin.terminals.index'))->assertOk()->assertSee('4711');
    }

    public function test_pin_rules_are_enforced(): void {
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $this->employee->sqid, 'pin' => '12', 'pin_confirmation' => '12'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $this->employee->sqid, 'pin' => '1234', 'pin_confirmation' => '4321'])
            ->assertSessionHasErrors('pin');

        $withoutNumber = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $withoutNumber->sqid, 'pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertSessionHasErrors('user');

        $this->assertSame(0, UserTerminalPin::query()->count());
    }

    public function test_personnel_number_and_pin_clock_in_at_the_terminal(): void {
        app(TerminalPinService::class)->set($this->employee, '2468', $this->admin);

        $this->pinScan('4711', '2468')->assertOk()->assertJson(['status' => 'clocked_in']);

        $this->assertNotNull(Attendance::query()->where('user_id', $this->employee->id)->whereNull('ended_at')->first());
    }

    public function test_unknown_number_and_wrong_pin_answer_the_same(): void {
        app(TerminalPinService::class)->set($this->employee, '2468', $this->admin);

        $this->pinScan('9999', '2468')->assertOk()->assertJson(['status' => 'invalid_pin']);
        $this->pinScan('4711', '0000')->assertOk()->assertJson(['status' => 'invalid_pin']);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_pin_locks_after_five_failures_and_admin_can_unlock(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
        $pin = app(TerminalPinService::class)->set($this->employee, '2468', $this->admin);

        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '1111', 'e-' . $attempt)->assertJson(['status' => 'invalid_pin']);
        }
        $this->assertTrue($pin->fresh()?->isLocked());
        $this->assertSame(1, $pin->auditLogs()->where('event', 'terminal.pin_locked')->count());

        // Gesperrt: auch die richtige PIN wird abgewiesen.
        $this->pinScan('4711', '2468', 'e-right-locked')->assertJson(['status' => 'invalid_pin']);

        $this->actingAs($this->admin)->post(route('admin.terminals.pins.unlock'), ['pin' => $pin->sqid])->assertRedirect();
        $this->pinScan('4711', '2468', 'e-after-unlock')->assertJson(['status' => 'clocked_in']);

        Carbon::setTestNow();
    }

    public function test_lock_expires_after_the_lock_period(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
        app(TerminalPinService::class)->set($this->employee, '2468', $this->admin);
        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '1111', 'l-' . $attempt);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-17 08:16:00'));
        $this->pinScan('4711', '2468', 'l-later')->assertJson(['status' => 'clocked_in']);

        Carbon::setTestNow();
    }

    public function test_kiosk_offers_the_pin_path(): void {
        $this->get(route('kiosk.show', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('data-kiosk-pin-form', false)
            ->assertSee(__('terminal.kiosk.pin_toggle'));
    }

    private function pinScan(string $personnelNumber, string $pin, ?string $eventId = null): TestResponse {
        return $this->postJson(route('api.terminal.ingest', ['token' => $this->token]), array_filter([
            'personnel_number' => $personnelNumber,
            'pin' => $pin,
            'event_id' => $eventId,
        ]));
    }
}
