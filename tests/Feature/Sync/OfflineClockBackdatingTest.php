<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineClockBackdatingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sync;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Models\{Attendance, DayClosure, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-09: **der Offline-Weg war eine Hintertür an
 * der Serverzeit vorbei.**
 *
 * Online stempelt der Server die Zeit selbst und sperrt abgeschlossene Tage.
 * Der Sync-Befehl `attendance.clock-in` übernahm dagegen den Zeitstempel des
 * Geräts 1:1 — ohne Zukunftsverbot, ohne Altersgrenze, ohne Blick auf
 * Tages-/Monatsabschluss. Ein Mitarbeiter konnte sich damit nachträglich
 * Stunden in einen bereits freigegebenen und exportierten Monat schreiben.
 *
 * Für Änderungen an gesperrten Tagen gibt es den Weg über
 * `attendance.correct` — mit Begründung und Genehmigung.
 */
class OfflineClockBackdatingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $payload */
    private function clockIn(array $payload): TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => (string) Str::uuid(),
                'type' => 'attendance.clock-in',
                'payload' => $payload,
            ]],
        ]);
    }

    public function test_zeitstempel_aus_der_zukunft_wird_abgewiesen(): void {
        $this->clockIn(['started_at' => now()->addHours(5)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_zu_alter_zeitstempel_wird_abgewiesen(): void {
        // Ein Gerät kann tagelang offline sein — ein halbes Jahr nicht.
        $this->clockIn(['started_at' => now()->subMonths(6)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_stempel_im_offenen_fenster_wird_uebernommen(): void {
        // Gegenprobe: der eigentliche Zweck des Offline-Betriebs muss bleiben.
        $this->clockIn(['started_at' => now()->subHours(3)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied');

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_abgeschlossener_tag_nimmt_keine_neue_stempelung_mehr(): void {
        // Der Kern des Befunds: der Tag ist abgeschlossen, der Sync legte
        // trotzdem eine neue Stempelung an — inklusive Wirkung auf Gleitzeit
        // und Überstundenzuschlag.
        $tag = now()->subDays(3)->startOfDay();

        DayClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'day' => $tag->toDateString(),
            'status' => DayClosureStatus::Closed,
            'closed_at' => now()->subDays(2),
        ]);

        $this->clockIn(['started_at' => $tag->copy()->addHours(8)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_zweiter_stempel_ueber_einen_bestehenden_wird_abgewiesen(): void {
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHours(6),
            'ended_at' => now()->subHours(2),
            'date' => now()->toDateString(),
        ]);

        $this->clockIn(['started_at' => now()->subHours(4)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(1, Attendance::query()->count());
    }
}
