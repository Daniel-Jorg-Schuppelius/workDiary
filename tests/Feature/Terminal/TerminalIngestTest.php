<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalIngestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Terminal;

use App\Enums\Attendance\AttendanceSource;
use App\Models\{Attendance, AttendanceTerminal, User, UserBadge};
use App\Services\Reporting\WorkBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 061, MVP-130: Terminal-Ingest. Prüft Token-Auth, Badge→Kommen/Gehen
 * über die bestehende Anwesenheitslogik (Quelle `terminal`), Offline-Dedup über
 * die Ereignis-ID sowie die Abweisung von fremdem Token / unbekanntem und
 * gesperrtem Badge.
 */
final class TerminalIngestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BADGE = 'AB12-CD34';

    private string $token;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        [, $this->token] = AttendanceTerminal::issue($this->organization->id, 'Halle Nord');
        $this->assignBadge(self::BADGE);
    }

    private function assignBadge(string $uid): UserBadge {
        return UserBadge::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'label' => 'Test',
            'badge_hash' => UserBadge::hashBadge($uid),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function scan(array $payload, ?string $token = null): TestResponse {
        return $this->postJson('/api/terminal/ingest/' . ($token ?? $this->token), $payload);
    }

    public function test_invalid_token_is_rejected(): void {
        $this->scan(['badge_uid' => self::BADGE], token: 'term_wrong')->assertStatus(401);
    }

    public function test_missing_badge_is_rejected(): void {
        $this->scan([])->assertStatus(422);
    }

    public function test_badge_scan_clocks_in_then_out(): void {
        $this->scan(['badge_uid' => self::BADGE])->assertOk()->assertJsonPath('status', 'clocked_in');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($attendance->ended_at);
        $this->assertSame(AttendanceSource::Terminal, $attendance->source);

        $this->scan(['badge_uid' => self::BADGE])->assertOk()->assertJsonPath('status', 'clocked_out');
        $this->assertNotNull($attendance->refresh()->ended_at);
    }

    public function test_offline_event_is_deduplicated(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in', 'event_id' => 'evt-1'])->assertJsonPath('status', 'clocked_in');
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in', 'event_id' => 'evt-1'])->assertJsonPath('status', 'skipped');

        $this->assertSame(1, Attendance::query()->where('user_id', $this->user->id)->count());
    }

    public function test_offline_event_keeps_original_timestamp(): void {
        $this->scan([
            'badge_uid' => self::BADGE,
            'event' => 'in',
            'occurred_at' => '2026-07-01 08:00:00',
            'event_id' => 'evt-2',
        ])->assertJsonPath('status', 'clocked_in');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertNotNull($attendance->started_at);
        // Originalzeit übernommen (Round-Trip in der App-Zeitzone).
        $this->assertSame('2026-07-01 08:00', $attendance->started_at->format('Y-m-d H:i'));
    }

    public function test_unknown_badge_is_rejected(): void {
        $this->scan(['badge_uid' => 'FF-FF-FF'])->assertOk()->assertJsonPath('status', 'unknown_badge');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_revoked_badge_is_rejected(): void {
        UserBadge::query()->where('user_id', $this->user->id)->update(['revoked_at' => now()]);

        $this->scan(['badge_uid' => self::BADGE])->assertJsonPath('status', 'unknown_badge');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_work_break_work_toggle_tracks_break_minutes(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in', 'occurred_at' => '2026-07-01 08:00:00'])
            ->assertJsonPath('status', 'clocked_in');

        // Pausen-Scan startet die Pause.
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'occurred_at' => '2026-07-01 10:00:00'])
            ->assertOk()->assertJsonPath('status', 'break_started');
        $attendance = Attendance::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertTrue($attendance->isOnBreak());

        // Nächster Pausen-Scan beendet sie (30 Min).
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'occurred_at' => '2026-07-01 10:30:00'])
            ->assertJsonPath('status', 'break_ended');
        $attendance->refresh();
        $this->assertFalse($attendance->isOnBreak());
        $this->assertSame(30, $attendance->break_minutes_manual);

        // Gehen um 13:00 → 5h brutto − 30 Min Pause = 270 Min (unter ArbZG-Schwelle, kein Auto-Break).
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'out', 'occurred_at' => '2026-07-01 13:00:00'])
            ->assertJsonPath('status', 'clocked_out');
        $attendance->refresh();
        $this->assertSame(30, $attendance->break_minutes_manual);
        $this->assertSame(0, $attendance->break_minutes_auto);
        $this->assertSame(270, $attendance->duration_minutes);
    }

    public function test_break_without_open_attendance_is_noop(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break'])
            ->assertOk()->assertJsonPath('status', 'noop');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_break_event_is_deduplicated(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in'])->assertJsonPath('status', 'clocked_in');
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'event_id' => 'brk-1'])->assertJsonPath('status', 'break_started');
        // Erneut zugestellt → übersprungen, Pause NICHT versehentlich beendet.
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'event_id' => 'brk-1'])->assertJsonPath('status', 'skipped');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertTrue($attendance->isOnBreak());
        $this->assertSame(0, $attendance->break_minutes_manual);
    }

    public function test_clock_out_finalizes_running_break(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in', 'occurred_at' => '2026-07-01 08:00:00'])->assertJsonPath('status', 'clocked_in');
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'occurred_at' => '2026-07-01 10:00:00'])->assertJsonPath('status', 'break_started');

        // Ausstempeln bei laufender Pause um 12:00 → offene Pause (120 Min) wird beendet.
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'out', 'occurred_at' => '2026-07-01 12:00:00'])->assertJsonPath('status', 'clocked_out');

        $attendance = Attendance::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($attendance->break_started_at);
        $this->assertSame(120, $attendance->break_minutes_manual);
        $this->assertSame(120, $attendance->duration_minutes); // 240 brutto − 120 Pause
    }

    public function test_default_event_type_is_work(): void {
        // Ohne event_type verhält sich der Scan wie bisher (Kommen).
        $this->scan(['badge_uid' => self::BADGE])->assertJsonPath('status', 'clocked_in');
        $this->assertSame(1, Attendance::query()->where('user_id', $this->user->id)->whereNull('ended_at')->count());
    }

    public function test_break_reduces_reported_work_time(): void {
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'in', 'occurred_at' => '2026-07-01 08:00:00'])->assertJsonPath('status', 'clocked_in');
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'occurred_at' => '2026-07-01 10:00:00'])->assertJsonPath('status', 'break_started');
        $this->scan(['badge_uid' => self::BADGE, 'event_type' => 'break', 'occurred_at' => '2026-07-01 10:30:00'])->assertJsonPath('status', 'break_ended');
        $this->scan(['badge_uid' => self::BADGE, 'event' => 'out', 'occurred_at' => '2026-07-01 13:00:00'])->assertJsonPath('status', 'clocked_out');

        $balance = app(WorkBalanceCalculator::class)->daily($this->user, Carbon::parse('2026-07-01'));
        $this->assertSame(30, $balance->breakMinutes);
    }
}
