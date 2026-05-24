<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftLinkingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{DiaryEntry, EmergencyAssignment, OnCallShift, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftLinkingTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_user_owns_shifts_assignments_and_entries(): void {
        $user = User::factory()->user()->create();

        $shift = OnCallShift::factory()->for($user)->create();
        $assignment = EmergencyAssignment::factory()
            ->for($user)
            ->for($shift, 'shift')
            ->create();
        $entry = DiaryEntry::create([
            'user_id' => $user->id,
            'on_call_shift_id' => $shift->id,
            'emergency_assignment_id' => $assignment->id,
            'content' => 'something happened',
            'status' => 2,
            'start_at' => now(),
        ]);

        $this->assertTrue($user->onCallShifts()->whereKey($shift->id)->exists());
        $this->assertTrue($user->emergencyAssignments()->whereKey($assignment->id)->exists());
        $this->assertSame($shift->id, $entry->shift->id);
        $this->assertSame($assignment->id, $entry->emergency->id);
        $this->assertSame($shift->id, $assignment->shift->id);
        $this->assertTrue($shift->diaryEntries()->whereKey($entry->id)->exists());
        $this->assertTrue($assignment->diaryEntries()->whereKey($entry->id)->exists());
    }

    public function test_overlapping_scope_finds_shifts_in_period(): void {
        $user = User::factory()->user()->create();

        $inside = OnCallShift::factory()->for($user)->create([
            'start_at' => '2026-04-29 08:00:00',
            'end_at' => '2026-04-29 16:00:00',
        ]);
        OnCallShift::factory()->for($user)->create([
            'start_at' => '2026-05-01 08:00:00',
            'end_at' => '2026-05-01 16:00:00',
        ]);

        $found = OnCallShift::query()
            ->overlapping(
                new \DateTimeImmutable('2026-04-29 12:00:00'),
                new \DateTimeImmutable('2026-04-29 14:00:00'),
            )
            ->pluck('id')
            ->all();

        $this->assertSame([$inside->id], $found);
    }

    public function test_owner_policy_for_shift_and_assignment(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $admin = User::factory()->admin()->create();

        $shift = OnCallShift::factory()->for($owner)->create();
        $assignment = EmergencyAssignment::factory()->for($owner)->create();

        $this->assertTrue($owner->can('update', $shift));
        $this->assertFalse($other->can('update', $shift));
        $this->assertTrue($admin->can('delete', $shift));

        $this->assertTrue($owner->can('view', $assignment));
        $this->assertFalse($other->can('view', $assignment));
        $this->assertTrue($admin->can('update', $assignment));
    }

    public function test_deleting_shift_nulls_links_on_entries_and_assignments(): void {
        $user = User::factory()->user()->create();
        $shift = OnCallShift::factory()->for($user)->create();
        $assignment = EmergencyAssignment::factory()
            ->for($user)
            ->for($shift, 'shift')
            ->create();
        $entry = DiaryEntry::create([
            'user_id' => $user->id,
            'on_call_shift_id' => $shift->id,
            'content' => 'x',
            'status' => 2,
            'start_at' => now(),
        ]);

        $shift->delete();

        $this->assertNull($entry->fresh()->on_call_shift_id);
        $this->assertNull($assignment->fresh()->on_call_shift_id);
    }
}
