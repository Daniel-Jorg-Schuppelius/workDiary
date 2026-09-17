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
            'pin' => '246813',
            'pin_confirmation' => '246813',
        ])->assertRedirect()->assertSessionHas('success');

        $stored = (string) DB::table('user_terminal_pins')->value('pin_hash');
        $this->assertNotSame('246813', $stored);
        $this->assertStringNotContainsString('246813', $stored);
        $this->assertSame(1, UserTerminalPin::query()->sole()->auditLogs()->where('event', 'terminal.pin_set')->count());

        $this->actingAs($this->admin)->get(route('admin.terminals.index'))->assertOk()->assertSee('4711');
    }

    public function test_pin_rules_are_enforced(): void {
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $this->employee->sqid, 'pin' => '12', 'pin_confirmation' => '12'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $this->employee->sqid, 'pin' => '123456', 'pin_confirmation' => '654321'])
            ->assertSessionHasErrors('pin');

        $withoutNumber = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.store'), ['user' => $withoutNumber->sqid, 'pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertSessionHasErrors('user');

        $this->assertSame(0, UserTerminalPin::query()->count());
    }

    public function test_personnel_number_and_pin_clock_in_at_the_terminal(): void {
        app(TerminalPinService::class)->set($this->employee, '246813', $this->admin);

        $this->pinScan('4711', '246813')->assertOk()->assertJson(['status' => 'clocked_in']);

        $this->assertNotNull(Attendance::query()->where('user_id', $this->employee->id)->whereNull('ended_at')->first());
    }

    public function test_unknown_number_and_wrong_pin_answer_the_same(): void {
        app(TerminalPinService::class)->set($this->employee, '246813', $this->admin);

        $this->pinScan('9999', '246813')->assertOk()->assertJson(['status' => 'invalid_pin']);
        $this->pinScan('4711', '000000')->assertOk()->assertJson(['status' => 'invalid_pin']);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_pin_locks_after_five_failures_and_admin_can_unlock(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
        $pin = app(TerminalPinService::class)->set($this->employee, '246813', $this->admin);

        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '111111', 'e-' . $attempt)->assertJson(['status' => 'invalid_pin']);
        }
        $this->assertTrue($pin->fresh()?->isLocked());
        $this->assertSame(1, $pin->auditLogs()->where('event', 'terminal.pin_locked')->count());

        // Gesperrt: auch die richtige PIN wird abgewiesen.
        $this->pinScan('4711', '246813', 'e-right-locked')->assertJson(['status' => 'invalid_pin']);

        $this->actingAs($this->admin)->post(route('admin.terminals.pins.unlock'), ['pin' => $pin->sqid])->assertRedirect();
        $this->pinScan('4711', '246813', 'e-after-unlock')->assertJson(['status' => 'clocked_in']);

        Carbon::setTestNow();
    }

    public function test_lock_expires_after_the_lock_period(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
        app(TerminalPinService::class)->set($this->employee, '246813', $this->admin);
        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '111111', 'l-' . $attempt);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-17 08:16:00'));
        $this->pinScan('4711', '246813', 'l-later')->assertJson(['status' => 'clocked_in']);

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

    /**
     * Sicherheitsaudit 2026-09-17 (kiosk-1): Der Fehlzähler wurde mit jeder
     * Sperre zurückgesetzt — eine kurze PIN war damit dauerhaft ratbar. Jetzt
     * zählt er weiter und die Sperre wächst.
     */
    public function test_repeated_lockouts_escalate_and_finally_hold(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00'));
        $pin = app(TerminalPinService::class)->set($this->employee, '246813', $this->admin);

        // Erste Sperre: 15 Minuten.
        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '111111', 'esk-a-' . $attempt);
        }
        $this->assertSame(TerminalPinService::MAX_ATTEMPTS, (int) $pin->fresh()?->failed_attempts);

        // Zweite Sperre nach Ablauf: eine Stunde.
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:16:00'));
        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '111111', 'esk-b-' . $attempt);
        }
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:32:00'));
        $this->pinScan('4711', '246813', 'esk-b-right')->assertJson(['status' => 'invalid_pin']);

        // Dritte Runde: einen Tag gesperrt — Raten wird damit sinnlos.
        Carbon::setTestNow(Carbon::parse('2026-09-17 09:32:00'));
        foreach (range(1, TerminalPinService::MAX_ATTEMPTS) as $attempt) {
            $this->pinScan('4711', '111111', 'esk-c-' . $attempt);
        }
        $this->assertSame(3 * TerminalPinService::MAX_ATTEMPTS, (int) $pin->fresh()?->failed_attempts);

        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00'));
        $this->pinScan('4711', '246813', 'esk-c-right')->assertJson(['status' => 'invalid_pin']);

        // Die Verwaltung kann jederzeit entsperren.
        $this->actingAs($this->admin)->post(route('admin.terminals.pins.unlock'), ['pin' => $pin->sqid])->assertRedirect();
        $this->pinScan('4711', '246813', 'esk-unlocked')->assertJson(['status' => 'clocked_in']);

        Carbon::setTestNow();
    }
}
