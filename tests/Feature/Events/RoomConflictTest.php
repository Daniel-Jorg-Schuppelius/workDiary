<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use App\Services\Event\RoomBookingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RoomConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Room $room;

    private RoomBookingService $svc;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->room = Room::factory()->create(['organization_id' => $this->organization->id]);
        $this->svc = app(RoomBookingService::class);
        config()->set('events.room_conflict_mode', 'hard');
    }

    public function test_no_conflict_for_free_slot(): void {
        $start = now()->addDay()->setTime(9, 0);
        $end = (clone $start)->addHour();

        $this->assertSame(0, $this->svc->findConflicts($this->room, $start, $end)->count());
    }

    public function test_detects_overlap_with_existing_booking(): void {
        $existing = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);
        $start = now()->addDay()->setTime(9, 0);
        $end = (clone $start)->addHours(2);
        $this->svc->attach($existing, $this->room, $start, $end);

        $overlapStart = (clone $start)->addMinutes(30);
        $overlapEnd = (clone $end)->addMinutes(30);

        $conflicts = $this->svc->findConflicts($this->room, $overlapStart, $overlapEnd);
        $this->assertSame(1, $conflicts->count());
        $this->assertSame($existing->id, $conflicts->first()->id);
    }

    public function test_attach_throws_on_hard_conflict(): void {
        $first = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);
        $second = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);
        $start = now()->addDay()->setTime(9, 0);
        $end = (clone $start)->addHours(2);
        $this->svc->attach($first, $this->room, $start, $end);

        $this->expectException(RuntimeException::class);
        $this->svc->attach($second, $this->room, (clone $start)->addMinutes(30), (clone $end)->addMinutes(30));
    }

    public function test_setup_buffer_extends_block(): void {
        $existing = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
        ]);
        $start = now()->addDay()->setTime(10, 0);
        $end = (clone $start)->addHour();
        // Bestehende Buchung 10:00-11:00 mit 30 Min Teardown -> blockiert bis 11:30
        $this->svc->attach($existing, $this->room, $start, $end, 0, 30);

        // Anfrage 11:15-12:00 ohne Puffer -> sollte trotzdem kollidieren
        $conflicts = $this->svc->findConflicts(
            $this->room,
            (clone $start)->addMinutes(75),
            (clone $start)->addMinutes(120),
        );

        $this->assertSame(1, $conflicts->count());
    }
}
