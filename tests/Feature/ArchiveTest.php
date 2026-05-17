<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArchiveTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use App\Services\Archive\ArchiveService;
use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config()->set('archive.threshold_days', 30);
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_archive_service_archives_old_done_entries_only(): void
    {
        $user = User::factory()->user()->create();
        $now = CarbonImmutable::parse('2026-04-30 12:00');

        $oldDone = DiaryEntry::factory()->for($user)->create([
            'status' => -1,
            'updated_at' => $now->subDays(40),
            'start_at' => $now->subDays(40),
        ]);
        $oldOpen = DiaryEntry::factory()->for($user)->create([
            'status' => 2,
            'updated_at' => $now->subDays(40),
            'start_at' => $now->subDays(40),
        ]);
        $recentDone = DiaryEntry::factory()->for($user)->create([
            'status' => -1,
            'updated_at' => $now->subDays(10),
            'start_at' => $now->subDays(10),
        ]);

        $result = app(ArchiveService::class)->run(30, $now);

        $this->assertSame(1, $result['diary']);
        $this->assertTrue($oldDone->fresh()->is_archived);
        $this->assertFalse($oldOpen->fresh()->is_archived);
        $this->assertFalse($recentDone->fresh()->is_archived);
    }

    public function test_archive_service_archives_old_shifts_and_assignments(): void
    {
        $user = User::factory()->user()->create();
        $now = CarbonImmutable::parse('2026-04-30 12:00');

        $oldShift = OnCallShift::factory()->for($user)->create([
            'start_at' => $now->subDays(50),
            'end_at' => $now->subDays(45),
        ]);
        $recentShift = OnCallShift::factory()->for($user)->create([
            'start_at' => $now->subDays(10),
            'end_at' => $now->subDays(5),
        ]);
        $oldAssignment = EmergencyAssignment::factory()->for($user)->create([
            'start_at' => $now->subDays(50),
            'end_at' => $now->subDays(45),
        ]);

        $result = app(ArchiveService::class)->run(30, $now);

        $this->assertSame(1, $result['shifts']);
        $this->assertSame(1, $result['assignments']);
        $this->assertTrue($oldShift->fresh()->is_archived);
        $this->assertFalse($recentShift->fresh()->is_archived);
        $this->assertTrue($oldAssignment->fresh()->is_archived);
    }

    public function test_owner_can_archive_and_restore_entry(): void
    {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create(['status' => -1]);

        $this->actingAs($user)
            ->post(route('diary.archive', $entry))
            ->assertRedirect(route('diary.show', $entry));

        $this->assertTrue($entry->fresh()->is_archived);
        $this->assertNotNull($entry->fresh()->archived_at);

        $this->actingAs($user)
            ->post(route('diary.restore', $entry))
            ->assertRedirect(route('diary.show', $entry));

        $this->assertFalse($entry->fresh()->is_archived);
        $this->assertNull($entry->fresh()->archived_at);
    }

    public function test_other_user_cannot_archive_foreign_entry(): void
    {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post(route('diary.archive', $entry))
            ->assertForbidden();
    }

    public function test_admin_can_archive_any_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->post(route('diary.archive', $entry))
            ->assertRedirect();

        $this->assertTrue($entry->fresh()->is_archived);
    }

    public function test_index_excludes_archived_by_default_and_includes_with_filter(): void
    {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->for($user)->create(['is_archived' => false, 'content' => 'Sichtbar Eintrag']);
        DiaryEntry::factory()->for($user)->create(['is_archived' => true, 'archived_at' => now(), 'content' => 'Archiviert Eintrag']);

        $this->actingAs($user)
            ->get(route('diary.index'))
            ->assertOk()
            ->assertSee('Sichtbar Eintrag')
            ->assertDontSee('Archiviert Eintrag');

        $this->actingAs($user)
            ->get(route('diary.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Sichtbar Eintrag')
            ->assertSee('Archiviert Eintrag');
    }

    public function test_admin_can_trigger_archive_run_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('archive.run'))
            ->assertRedirect();
    }

    public function test_non_admin_cannot_trigger_archive_run_route(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('archive.run'))
            ->assertForbidden();
    }

    public function test_archive_run_command_executes(): void
    {
        $this->artisan('archive:run', ['--days' => 30])
            ->assertSuccessful();
    }
}
