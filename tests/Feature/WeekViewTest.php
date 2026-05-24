<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{DiaryEntry, EmergencyAssignment, OnCallShift, User};
use App\Services\Calendar\WeekViewService;
use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeekViewTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_week_route_requires_auth(): void {
        $this->get('/week')->assertRedirect(route('login'));
    }

    public function test_mine_scope_only_returns_users_items(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();

        $monday = CarbonImmutable::parse('2026-04-27 00:00');

        $myShift = OnCallShift::factory()->for($owner)->create([
            'start_at' => $monday->setTime(8, 0),
            'end_at' => $monday->setTime(16, 0),
        ]);
        OnCallShift::factory()->for($other)->create([
            'start_at' => $monday->setTime(8, 0),
            'end_at' => $monday->setTime(16, 0),
        ]);

        $service = app(WeekViewService::class);
        $data = $service->build($monday, $owner, teamScope: false);

        $this->assertCount(1, $data['shifts']);
        $this->assertSame($myShift->id, $data['shifts']->first()->id);
    }

    public function test_team_scope_returns_all_users_items(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $monday = CarbonImmutable::parse('2026-04-27 00:00');

        OnCallShift::factory()->for($owner)->create([
            'start_at' => $monday->setTime(8, 0),
            'end_at' => $monday->setTime(16, 0),
        ]);
        OnCallShift::factory()->for($other)->create([
            'start_at' => $monday->setTime(8, 0),
            'end_at' => $monday->setTime(16, 0),
        ]);

        $data = app(WeekViewService::class)->build($monday, $owner, teamScope: true);

        $this->assertCount(2, $data['shifts']);
    }

    public function test_group_by_day_splits_multi_day_shift(): void {
        $owner = User::factory()->user()->create();
        $monday = CarbonImmutable::parse('2026-04-27 00:00');

        $shift = OnCallShift::factory()->for($owner)->create([
            'start_at' => $monday->setTime(20, 0),
            'end_at' => $monday->addDays(2)->setTime(8, 0),
        ]);

        $service = app(WeekViewService::class);
        $data = $service->build($monday, $owner, teamScope: false);
        $byDay = $service->groupByDay($data['shifts'], $data['start']);

        $this->assertCount(1, $byDay[0]);
        $this->assertCount(1, $byDay[1]);
        $this->assertCount(1, $byDay[2]);
        $this->assertCount(0, $byDay[3]);
        $this->assertSame($shift->id, $byDay[0]->first()->id);
    }

    public function test_placement_calculates_top_and_height_in_percent(): void {
        $day = CarbonImmutable::parse('2026-04-27 00:00');
        $start = $day->setTime(6, 0);
        $end = $day->setTime(18, 0);

        $p = app(WeekViewService::class)->placement($start, $end, $day);

        $this->assertSame(25.0, $p['top']);   // 6/24
        $this->assertSame(50.0, $p['height']); // 12/24
    }

    public function test_archived_items_are_excluded(): void {
        $owner = User::factory()->user()->create();
        $monday = CarbonImmutable::parse('2026-04-27 00:00');

        OnCallShift::factory()->for($owner)->create([
            'start_at' => $monday,
            'end_at' => $monday->addHours(8),
            'is_archived' => true,
        ]);
        EmergencyAssignment::factory()->for($owner)->create([
            'start_at' => $monday->addHour(),
            'end_at' => $monday->addHours(2),
            'is_archived' => true,
        ]);
        DiaryEntry::create([
            'user_id' => $owner->id,
            'content' => 'archived',
            'status' => 2,
            'start_at' => $monday->addHours(3),
            'is_archived' => true,
        ]);

        $data = app(WeekViewService::class)->build($monday, $owner, teamScope: true);

        $this->assertCount(0, $data['shifts']);
        $this->assertCount(0, $data['assignments']);
        $this->assertCount(0, $data['entries']);
    }

    public function test_view_renders_with_my_and_team_scope(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('week.index'))
            ->assertOk()
            ->assertSee('Meine Woche');

        $this->actingAs($user)
            ->get(route('week.index', ['scope' => 'team']))
            ->assertOk()
            ->assertSee('Team-Woche');
    }

    public function test_multi_week_range_renders_week_tabs(): void {
        $user = User::factory()->user()->create();

        // Globaler Range über zwei volle ISO-Wochen
        app(DateRangeContext::class)->set(
            DateRangeContext::PRESET_CUSTOM,
            '2026-04-27', // KW 18/2026 Mo
            '2026-05-10', // KW 19/2026 So
        );

        $response = $this->actingAs($user)->get(route('week.index'));

        $response->assertOk();
        $response->assertSee('KW 18');
        $response->assertSee('KW 19');
    }

    public function test_date_query_param_redirects_for_backward_compat(): void {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('week.index', ['date' => '2026-04-29']));

        $response->assertRedirect(route('week.index'));

        $state = app(DateRangeContext::class)->current();
        $this->assertSame('2026-04-27', $state['from']->toDateString());
        $this->assertSame('2026-05-03', $state['to']->toDateString());
    }
}
