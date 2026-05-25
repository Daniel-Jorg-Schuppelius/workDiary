<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{OnCallShift, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CalendarTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_index_renders_for_authenticated_user(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('id="calendar"', false);
    }

    public function test_events_endpoint_returns_users_own_shifts(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $start = now()->startOfWeek()->addDay()->setTime(8, 0);
        $end = (clone $start)->addHours(4);

        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);
        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $other->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $rangeStart = $start->copy()->subDays(7)->toIso8601String();
        $rangeEnd = $start->copy()->addDays(7)->toIso8601String();

        $res = $this->actingAs($user)
            ->getJson(route('calendar.events', ['start' => $rangeStart, 'end' => $rangeEnd]));

        $res->assertOk()->assertJsonCount(1);
        $this->assertStringStartsWith('shift-', $res->json('0.id'));
    }

    public function test_events_endpoint_requires_auth(): void {
        $this->getJson(route('calendar.events'))->assertStatus(401);
    }
}
