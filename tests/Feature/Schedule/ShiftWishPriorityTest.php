<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftWishPriorityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ShiftPreference;
use App\Models\{DesiredShift, ScheduledShift, ShiftType, User};
use App\Services\Schedule\{StaffingSuggester, WishMatcher};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP-515 (Feature 103): Wunsch-Prioritäten, expliziter Freiwunsch (`off`)
 * und die Erfüllt-/Konflikt-Marker der Planung.
 */
class ShiftWishPriorityTest extends TestCase {
    use RefreshDatabase;

    private function shiftType(int $orgId): ShiftType {
        return ShiftType::factory()->create([
            'organization_id' => $orgId,
            'is_active' => true,
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);
    }

    public function test_day_off_wish_can_be_stored_and_rejects_shift_type(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('schedule.availability.desired.store'), [
                'date' => '2026-09-07',
                'preference' => ShiftPreference::PreferredOff->value,
                'priority' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('desired_shifts', [
            'user_id' => $user->id,
            'preference' => 'off',
            'priority' => 1,
        ]);

        $type = $this->shiftType((int) $user->organization_id);
        $this->actingAs($user)
            ->post(route('schedule.availability.desired.store'), [
                'date' => '2026-09-08',
                'preference' => ShiftPreference::PreferredOff->value,
                'shift_type_id' => $type->sqid,
            ])
            ->assertSessionHasErrors('shift_type_id');
    }

    public function test_suggester_excludes_day_off_wish(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = $this->shiftType($orgId);

        $offUser = User::factory()->user()->create(['organization_id' => $orgId]);
        DesiredShift::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $offUser->id,
            'date' => '2026-09-07',
            'shift_type_id' => null,
            'preference' => ShiftPreference::PreferredOff->value,
        ]);
        $neutral = User::factory()->user()->create(['organization_id' => $orgId]);

        $results = app(StaffingSuggester::class)->suggest(new CarbonImmutable('2026-09-07'), $type, $orgId);
        $ids = array_column($results, 'user_id');

        $this->assertNotContains($offUser->id, $ids);
        $this->assertContains($neutral->id, $ids);
    }

    public function test_higher_wish_priority_ranks_first(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = $this->shiftType($orgId);

        $high = User::factory()->user()->create(['organization_id' => $orgId, 'name' => 'Hoch']);
        $low = User::factory()->user()->create(['organization_id' => $orgId, 'name' => 'Niedrig']);
        foreach ([[$high, 1], [$low, 3]] as [$user, $priority]) {
            DesiredShift::factory()->create([
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'date' => '2026-09-07',
                'shift_type_id' => $type->id,
                'preference' => ShiftPreference::Want->value,
                'priority' => $priority,
            ]);
        }

        $results = app(StaffingSuggester::class)->suggest(new CarbonImmutable('2026-09-07'), $type, $orgId);
        $ids = array_column($results, 'user_id');

        $this->assertLessThan(
            array_search($low->id, $ids, true),
            array_search($high->id, $ids, true),
            'Wunsch mit Priorität 1 muss vor Priorität 3 ranken.',
        );
    }

    public function test_wish_matcher_marks_fulfilled_and_conflict(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = $this->shiftType($orgId);

        $happy = User::factory()->user()->create(['organization_id' => $orgId]);
        $blocked = User::factory()->user()->create(['organization_id' => $orgId]);

        DesiredShift::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $happy->id,
            'date' => '2026-09-07',
            'shift_type_id' => $type->id,
            'preference' => ShiftPreference::Want->value,
        ]);
        DesiredShift::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $blocked->id,
            'date' => '2026-09-07',
            'shift_type_id' => null,
            'preference' => ShiftPreference::PreferredOff->value,
            'priority' => 1,
        ]);

        $shifts = collect([
            ScheduledShift::factory()->create([
                'organization_id' => $orgId,
                'user_id' => $happy->id,
                'shift_type_id' => $type->id,
                'date' => '2026-09-07',
            ]),
            ScheduledShift::factory()->create([
                'organization_id' => $orgId,
                'user_id' => $blocked->id,
                'shift_type_id' => $type->id,
                'date' => '2026-09-07',
            ]),
        ]);

        $markers = app(WishMatcher::class)->forShifts($shifts);

        $this->assertSame('fulfilled', $markers[(int) $shifts[0]->id]['state'] ?? null);
        $this->assertSame('conflict', $markers[(int) $shifts[1]->id]['state'] ?? null);
        $this->assertStringContainsString('1', $markers[(int) $shifts[1]->id]['label']);
    }
}
