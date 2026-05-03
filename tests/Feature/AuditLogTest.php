<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_creating_diary_entry_writes_audit_log(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);

        $entry = DiaryEntry::factory()->for($user)->create(['content' => 'Hallo']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => DiaryEntry::class,
            'auditable_id' => $entry->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_updating_diary_entry_writes_changes(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);
        $entry = DiaryEntry::factory()->for($user)->create(['content' => 'A']);

        $entry->update(['content' => 'B']);

        $log = AuditLog::where('event', 'updated')->where('auditable_id', $entry->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('A', $log->changes['before']['content']);
        $this->assertSame('B', $log->changes['after']['content']);
    }

    public function test_delete_writes_audit_log(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $id = $entry->id;
        $entry->delete();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'deleted',
            'auditable_type' => DiaryEntry::class,
            'auditable_id' => $id,
        ]);
    }

    public function test_audit_index_admin_only(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();
    }

    public function test_audit_filter_by_event_and_type(): void {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();
        $this->actingAs($user);
        $entry = DiaryEntry::factory()->for($user)->create();
        Comment::factory()->for($entry, 'diaryEntry')->for($user)->create();

        $this->actingAs($admin)
            ->get(route('audit.index', ['event' => 'created', 'type' => 'comment']))
            ->assertOk()
            ->assertSee('Comment');
    }
}
