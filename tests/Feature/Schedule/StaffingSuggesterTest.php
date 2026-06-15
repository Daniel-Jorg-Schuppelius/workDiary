<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaffingSuggesterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{AvailabilityWindow, Qualification, ScheduledShift, ShiftType, User};
use App\Services\Schedule\StaffingSuggester;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffingSuggesterTest extends TestCase {
    use RefreshDatabase;

    private function suggester(): StaffingSuggester {
        return app(StaffingSuggester::class);
    }

    public function test_suggests_available_and_qualified_candidates(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $date = '2026-07-06'; // Monday

        $type = ShiftType::factory()->create([
            'organization_id' => $orgId,
            'is_active' => true,
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);

        $available = User::factory()->user()->create(['organization_id' => $orgId]);
        AvailabilityWindow::factory()->preferred()->forWeekday(1)->create([
            'organization_id' => $orgId,
            'user_id' => $available->id,
        ]);

        $neutral = User::factory()->user()->create(['organization_id' => $orgId]);

        $results = $this->suggester()->suggest(
            new CarbonImmutable($date),
            $type,
            $orgId,
        );

        $ids = array_column($results, 'user_id');
        $this->assertContains($available->id, $ids);
        $this->assertContains($neutral->id, $ids);

        // Verfügbarer/bevorzugter Kandidat rankt vor neutralem.
        $availIndex = array_search($available->id, $ids, true);
        $neutralIndex = array_search($neutral->id, $ids, true);
        $this->assertLessThan($neutralIndex, $availIndex);
    }

    public function test_excludes_unavailable_candidate(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = ShiftType::factory()->create([
            'organization_id' => $orgId,
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);

        $blocked = User::factory()->user()->create(['organization_id' => $orgId]);
        AvailabilityWindow::factory()->unavailable()->forWeekday(1)->create([
            'organization_id' => $orgId,
            'user_id' => $blocked->id,
        ]);

        $results = $this->suggester()->suggest(new CarbonImmutable('2026-07-06'), $type, $orgId);

        $this->assertNotContains($blocked->id, array_column($results, 'user_id'));
    }

    public function test_excludes_candidate_with_overlapping_shift(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;
        $type = ShiftType::factory()->create([
            'organization_id' => $orgId,
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);

        $busy = User::factory()->user()->create(['organization_id' => $orgId]);
        // Bestehende Schicht eines ANDEREN Typs am selben Tag, überschneidend.
        $otherType = ShiftType::factory()->create([
            'organization_id' => $orgId,
            'default_start_time' => '10:00:00',
            'default_end_time' => '18:00:00',
        ]);
        ScheduledShift::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $busy->id,
            'shift_type_id' => $otherType->id,
            'date' => '2026-07-06',
            'status' => ScheduledShiftStatus::Published->value,
        ]);

        $free = User::factory()->user()->create(['organization_id' => $orgId]);

        $results = $this->suggester()->suggest(new CarbonImmutable('2026-07-06'), $type, $orgId);
        $ids = array_column($results, 'user_id');

        $this->assertNotContains($busy->id, $ids);
        $this->assertContains($free->id, $ids);
    }

    public function test_qualified_candidate_ranks_above_unqualified(): void {
        $admin = User::factory()->admin()->create();
        $orgId = (int) $admin->organization_id;

        $qualification = Qualification::factory()->create(['organization_id' => $orgId]);
        $type = ShiftType::factory()->create([
            'organization_id' => $orgId,
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
        ]);
        $type->qualifications()->attach($qualification->id);

        $qualified = User::factory()->user()->create(['organization_id' => $orgId]);
        $qualified->qualifications()->attach($qualification->id);

        $unqualified = User::factory()->user()->create(['organization_id' => $orgId]);

        $results = $this->suggester()->suggest(new CarbonImmutable('2026-07-06'), $type, $orgId);
        $ids = array_column($results, 'user_id');

        $qIndex = array_search($qualified->id, $ids, true);
        $uIndex = array_search($unqualified->id, $ids, true);

        $this->assertNotFalse($qIndex);
        $this->assertNotFalse($uIndex);
        $this->assertLessThan($uIndex, $qIndex);

        $qualifiedRow = $results[$qIndex];
        $this->assertTrue($qualifiedRow['qualified']);
    }
}
