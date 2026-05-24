<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCrudTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Enums\Event\{EventStatus, EventType, EventVisibility, ParticipantRole};
use App\Models\{Event, EventCategory, Room, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class EventCrudTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_admin_can_create_event_with_room_and_participants(): void {
        $this->actingAs($this->admin);
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $category = EventCategory::factory()->create(['organization_id' => $this->organization->id]);

        $start = now()->addDay()->setTime(10, 0);
        $end = (clone $start)->addHours(2);

        $response = $this->post(route('events.store'), [
            'title' => 'Pflichtschulung Brandschutz',
            'event_type' => EventType::Training->value,
            'status' => EventStatus::Planned->value,
            'visibility' => EventVisibility::Internal->value,
            'category_id' => $category->id,
            'started_at' => $start->format('Y-m-d H:i:s'),
            'ended_at' => $end->format('Y-m-d H:i:s'),
            'responsible_user_id' => $this->admin->id,
            'rooms' => [[
                'room_id' => $room->id,
                'started_at' => $start->format('Y-m-d H:i:s'),
                'ended_at' => $end->format('Y-m-d H:i:s'),
                'setup_minutes_before' => 15,
                'teardown_minutes_after' => 15,
            ]],
            'participants' => [[
                'user_id' => $this->user->id,
                'role' => ParticipantRole::Attendee->value,
            ]],
        ]);

        $event = Event::query()->where('title', 'Pflichtschulung Brandschutz')->firstOrFail();
        $response->assertRedirect(route('events.show', $event));

        $this->assertSame($this->organization->id, $event->organization_id);
        $this->assertSame(1, $event->rooms()->count());
        $this->assertSame(1, $event->participants()->count());
    }

    public function test_regular_user_cannot_create_event(): void {
        $this->actingAs($this->user);

        $this->post(route('events.store'), [
            'title' => 'Verboten',
            'event_type' => EventType::Meeting->value,
            'status' => EventStatus::Planned->value,
            'visibility' => EventVisibility::Internal->value,
            'started_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ended_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->assertSame(0, Event::query()->count());
    }

    public function test_index_renders_for_authenticated_user(): void {
        $this->actingAs($this->user);
        Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $this->get(route('events.index'))->assertOk();
    }

    public function test_calendar_renders(): void {
        $this->actingAs($this->user);
        $this->get(route('events.calendar'))->assertOk();
    }

    public function test_admin_can_cancel_event(): void {
        $this->actingAs($this->admin);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->admin->id,
        ]);

        $this->patch(route('events.cancel', $event), ['cancel_reason' => 'Krankheit'])
            ->assertRedirect();

        $event->refresh();
        $this->assertNotNull($event->cancelled_at);
        $this->assertSame(EventStatus::Cancelled, $event->status);
    }

    public function test_admin_can_destroy_event(): void {
        $this->actingAs($this->admin);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->admin->id,
        ]);

        $this->delete(route('events.destroy', $event))->assertRedirect(route('events.index'));
        $this->assertSame(0, Event::query()->count());
    }
}
