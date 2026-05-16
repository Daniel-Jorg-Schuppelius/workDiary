<?php

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Services\UI\DateRangeContext;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
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
