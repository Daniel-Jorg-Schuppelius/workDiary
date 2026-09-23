<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceMaterializationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Models\Calendar\Event;
use App\Models\Platform\User;
use App\Services\Event\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RecurrenceMaterializationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private RecurrenceService $svc;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->svc = app(RecurrenceService::class);
        config()->set('events.materialization_days', 365);
    }

    public function test_materializes_weekly_count_four(): void {
        $master = Event::factory()->recurring('FREQ=WEEKLY;COUNT=4')->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'started_at' => now()->addDay()->setTime(9, 0),
            'ended_at' => now()->addDay()->setTime(10, 0),
        ]);

        $created = $this->svc->materialize($master);

        // 4 Occurrences - Master selbst = 3 neue Events
        $this->assertSame(3, $created);
        $this->assertSame(3, Event::query()->where('series_id', $master->id)->count());
    }

    /** MVP-823: gespeichert wird UTC, die Serie rechnet in der Termin-Zeitzone — 9 Uhr bleibt über den Zeitwechsel 9 Uhr. */
    public function test_weekly_series_keeps_local_time_across_daylight_saving_change(): void {
        $master = Event::factory()->recurring('FREQ=WEEKLY;COUNT=3')->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'timezone' => 'Europe/Berlin',
            'started_at' => '2030-10-20 07:00:00', // 09:00 MESZ
            'ended_at' => '2030-10-20 08:00:00',
        ]);

        $this->svc->materialize($master, \Illuminate\Support\Carbon::parse('2030-11-30'));

        $starts = Event::query()->where('series_id', $master->id)->orderBy('started_at')->get()
            ->map(fn (Event $e): string => $e->getRawOriginal('started_at'))->all();
        // 27.10. und 03.11. liegen in der Winterzeit: 09:00 MEZ = 08:00 UTC.
        $this->assertSame(['2030-10-27 08:00:00', '2030-11-03 08:00:00'], $starts);
    }

    public function test_materialize_is_idempotent(): void {
        $master = Event::factory()->recurring('FREQ=WEEKLY;COUNT=3')->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'started_at' => now()->addDay()->setTime(9, 0),
            'ended_at' => now()->addDay()->setTime(10, 0),
        ]);

        $first = $this->svc->materialize($master);
        $second = $this->svc->materialize($master);

        $this->assertSame(2, $first);
        $this->assertSame(0, $second);
        $this->assertSame(2, Event::query()->where('series_id', $master->id)->count());
    }

    public function test_expand_without_rule_returns_empty(): void {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $this->assertSame([], $this->svc->expand($event));
    }
}
