<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanbanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{DiaryEntry, User};
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        // Kanban liest den Range jetzt aus dem globalen Context.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_kanban_limits_entries_to_200_for_large_ranges(): void {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->count(250)->for($user)->create(['is_archived' => false]);

        $this->actingAs($user)
            ->get(route('kanban.index'))
            ->assertOk()
            ->assertViewHas('isLimited', true)
            ->assertViewHas('byStatus', function ($byStatus) {
                return $byStatus->flatten(1)->count() === 200;
            });
    }
}
