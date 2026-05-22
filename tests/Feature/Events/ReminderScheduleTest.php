<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderScheduleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventReminder;
use App\Models\User;
use App\Services\Event\ReminderService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ReminderScheduleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private ReminderService $svc;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->svc = app(ReminderService::class);
        config()->set('events.channels', ['mail', 'database']);
    }

    public function test_schedules_one_reminder_per_offset_and_channel(): void {
        $category = EventCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'reminder_offsets' => [10080, 1440],
        ]);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'category_id' => $category->id,
            'started_at' => now()->addMonth(),
            'ended_at' => now()->addMonth()->addHour(),
        ]);

        $created = $this->svc->scheduleFor($event);

        // 2 Offsets * 2 Channels
        $this->assertSame(4, $created);
        $this->assertSame(4, EventReminder::query()->where('event_id', $event->id)->count());
    }

    public function test_reschedule_replaces_unsent(): void {
        $category = EventCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'reminder_offsets' => [60],
        ]);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'category_id' => $category->id,
            'started_at' => now()->addMonth(),
            'ended_at' => now()->addMonth()->addHour(),
        ]);

        $this->svc->scheduleFor($event);
        $this->svc->scheduleFor($event);

        // Idempotent: weiterhin 1 Offset * 2 Channels = 2
        $this->assertSame(2, EventReminder::query()->where('event_id', $event->id)->count());
    }

    public function test_overrides_take_precedence_over_category(): void {
        $category = EventCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'reminder_offsets' => [10080, 1440, 60],
        ]);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'category_id' => $category->id,
            'started_at' => now()->addMonth(),
            'ended_at' => now()->addMonth()->addHour(),
            'reminder_overrides' => [30],
        ]);

        $offsets = $this->svc->effectiveOffsets($event);

        $this->assertSame([30], $offsets);
    }

    public function test_past_offsets_are_skipped(): void {
        $category = EventCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'reminder_offsets' => [10080], // 7 Tage vor Start
        ]);
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'responsible_user_id' => $this->user->id,
            'category_id' => $category->id,
            'started_at' => now()->addHour(), // 7-Tage-Offset liegt in der Vergangenheit
            'ended_at' => now()->addHours(2),
        ]);

        $created = $this->svc->scheduleFor($event);
        $this->assertSame(0, $created);
    }
}
