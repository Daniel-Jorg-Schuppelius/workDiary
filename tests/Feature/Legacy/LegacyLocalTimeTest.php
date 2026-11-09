<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyLocalTimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Support\LegacyClock;
use App\Models\Diary\{DiaryEntry, OnCallShift};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/**
 * Das Altsystem speichert Ortszeit ohne Zone (Befund 2026-11-09: der
 * Legacy-Bereich zeigte 10:00 als 11:00 bzw. 12:00, der Import übernahm
 * Ortszeit als UTC). Anzeige und „jetzt" laufen in Ortszeit, der Import
 * rechnet nach UTC um.
 */
class LegacyLocalTimeTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.display_timezone', 'Europe/Berlin');
        Config::set('app.legacy_write_enabled', true);
        // 22:30 UTC ist in Berlin (Sommerzeit) schon der nächste Tag, 00:30.
        Carbon::setTestNow(Carbon::parse('2026-07-01 22:30:00', 'UTC'));
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_clock_follows_the_installation_zone(): void {
        $this->assertSame('2026-07-02 00:30:00', LegacyClock::nowString());
        $this->assertSame('2026-07-02', LegacyClock::today()->toDateString());
        $this->assertSame('2026-07-01 08:00:00', LegacyClock::toUtc('2026-07-01 10:00:00'));
        $this->assertSame('2026-04-30 22:00:00', LegacyClock::toUtc('2026-05-01'));
    }

    public function test_diary_shows_stored_local_time_and_stores_local_now(): void {
        $admin = User::factory()->create(['legacy_user_id' => 1]);
        $id = (int) DB::connection('legacy')->table('tagebuch')->insertGetId([
            'user' => 1,
            'inhalt' => 'Kältemaschine prüfen',
            'von' => '2026-07-01 10:00:00',
            'bis' => '2026-07-01 10:30:00',
            'aktuell' => '2026-07-01 09:00:00',
            'gelesen' => 2,
        ]);

        $this->actingAs($admin)->get(route('legacy.diary.show', $id))
            ->assertOk()
            ->assertSee('10:00')
            ->assertSee('10:30')
            ->assertDontSee('12:30');

        $this->actingAs($admin)->post(route('legacy.diary.store'), [
            'inhalt' => 'Neuer Eintrag',
            'gelesen' => 2,
            'von' => '2026-07-02T08:00',
            'bis' => '2026-07-02T09:00',
        ])->assertRedirect();

        $this->assertSame('2026-07-02 00:30:00', DB::connection('legacy')->table('tagebuch')->where('inhalt', 'Neuer Eintrag')->value('aktuell'));
    }

    public function test_import_converts_local_time_to_utc(): void {
        $legacy = DB::connection('legacy');
        $legacy->table('user')->insert(['id' => 4, 'uname' => 'monteur', 'userpw' => 'x', 'email' => null]);
        $legacy->table('tagebuch')->insert([
            'id' => 70,
            'user' => 4,
            'inhalt' => 'Wartung',
            'von' => '2026-07-01 10:00:00',
            'bis' => '2026-07-01 12:00:00',
            'aktuell' => '2026-07-01 09:00:00',
            'gelesen' => 2,
        ]);
        $legacy->table('bereit')->insert(['id' => 80, 'user' => 4, 'von' => '2026-05-01', 'bis' => '2026-05-03']);
        User::factory()->create(['legacy_user_id' => 4]);

        $this->artisan('legacy:import', ['--diary' => true])->assertSuccessful();
        $this->artisan('legacy:import', ['--shifts' => true])->assertSuccessful();

        $entry = DiaryEntry::query()->withoutGlobalScopes()->where('legacy_id', 70)->sole();
        $this->assertSame('2026-07-01 08:00:00', $entry->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 10:00:00', $entry->end_at?->format('Y-m-d H:i:s'));

        $shift = OnCallShift::query()->withoutGlobalScopes()->where('legacy_id', 80)->sole();
        $this->assertSame('2026-04-30 22:00:00', $shift->start_at?->format('Y-m-d H:i:s'));
    }
}
