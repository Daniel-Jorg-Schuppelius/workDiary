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
use Illuminate\Testing\TestResponse;
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
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $category = EventCategory::factory()->create(['organization_id' => $this->organization->id]);

        $start = now()->addDay()->setTime(10, 0);
        $end = (clone $start)->addHours(2);

        $response = $this->postAsAdmin('events.store', [
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
        $this->postAsUser('events.store', [
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
        Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $this->getAsUser('events.index')->assertOk();
    }

    public function test_calendar_renders(): void {
        $this->getAsUser('events.calendar')->assertOk();
    }

    public function test_admin_can_cancel_event(): void {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->admin->id,
        ]);

        $this->patchAsAdmin('events.cancel', ['cancel_reason' => 'Krankheit'], $event)
            ->assertRedirect();

        $event->refresh();
        $this->assertNotNull($event->cancelled_at);
        $this->assertSame(EventStatus::Cancelled, $event->status);
    }

    public function test_admin_can_destroy_event(): void {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->admin->id,
        ]);

        $this->deleteAsAdmin('events.destroy', $event)->assertRedirect(route('events.index'));
        $this->assertSame(0, Event::query()->count());
    }

    private function getAsUser(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->user)->get(route($routeName, $parameters));
    }

    private function postAsUser(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->user)->post(route($routeName, $parameters), $payload);
    }

    private function postAsAdmin(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->post(route($routeName, $parameters), $payload);
    }

    private function patchAsAdmin(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->patch(route($routeName, $parameters), $payload);
    }

    private function deleteAsAdmin(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->delete(route($routeName, $parameters));
    }
}
