<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxStampTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Attendance\AttendanceSource;
use App\Models\{Attendance, User};
use App\Plugins\Fritzbox\FritzboxImportService;
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-534 (Feature 103, Q1-Drittabgleich): Telefonstempeln — Anrufe auf eine
 * Stempel-MSN werden zu Kommen-/Gehen-Stempeln; die Rufnummer des Anrufenden
 * wirkt als Ausweis (Q1 S. 57). Normale Anrufe bleiben unberührt.
 */
class FritzboxStampTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    private FritzboxImportService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->worker = $this->orgUser();
        $this->service = app(FritzboxImportService::class);
        $this->service->rememberStampNumber($this->organization, '+491512345678', $this->worker);
    }

    /** @return array<string, mixed> */
    private function config(): array {
        return [
            'default_user_id' => $this->orgUser()->id,
            'min_call_minutes' => '2',
            'call_lead_minutes' => '15',
            'stamp_toggle_line' => '999888',
        ];
    }

    private function callTo(string $ownLine, string $at, ?string $e164 = '+491512345678', int $type = FritzboxCall::TYPE_MISSED): FritzboxCall {
        $started = CarbonImmutable::parse($at);

        return new FritzboxCall(
            type: $type,
            direction: FritzboxCall::DIR_IN,
            startedAt: $started,
            endedAt: $started,
            durationMinutes: 0,
            numberRaw: $e164 ?? '',
            e164: $e164,
            name: null,
            ownLine: $ownLine,
        );
    }

    public function test_calls_to_stamp_line_toggle_attendance(): void {
        $result = $this->service->importCalls($this->organization, $this->config(), [
            $this->callTo('999888', '2026-08-10 08:00:00'),
            $this->callTo('999888', '2026-08-10 16:30:00'),
        ]);

        $this->assertSame(2, $result['stamped']);
        $attendance = Attendance::query()->where('user_id', $this->worker->id)->firstOrFail();
        $this->assertSame(AttendanceSource::Phone, $attendance->source);
        $this->assertSame('2026-08-10 08:00:00', $attendance->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-10 16:30:00', $attendance->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_reimport_is_idempotent(): void {
        $calls = [$this->callTo('999888', '2026-08-10 08:00:00')];
        $this->service->importCalls($this->organization, $this->config(), $calls);
        $result = $this->service->importCalls($this->organization, $this->config(), $calls);

        $this->assertSame(0, $result['stamped']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Attendance::query()->where('user_id', $this->worker->id)->count());
    }

    public function test_unknown_or_suppressed_numbers_are_ignored(): void {
        $result = $this->service->importCalls($this->organization, $this->config(), [
            $this->callTo('999888', '2026-08-10 08:00:00', '+493011111111'), // nicht zugeordnet
            $this->callTo('999888', '2026-08-10 08:05:00', null),            // unterdrückt
        ]);

        $this->assertSame(2, $result['ignored']);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_normal_calls_are_not_affected_by_stamp_config(): void {
        // Anruf auf eine NICHT-Stempel-Leitung mit unbekannter Nummer → normale
        // Pipeline (Inbox), kein Stempel.
        $call = new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: CarbonImmutable::parse('2026-08-10 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-10 09:10:00'),
            durationMinutes: 10,
            numberRaw: '+493022222222',
            e164: '+493022222222',
            name: null,
            ownLine: '555444',
        );

        $result = $this->service->importCalls($this->organization, $this->config(), [$call]);

        $this->assertSame(0, $result['stamped']);
        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, Attendance::query()->count());
    }
}
