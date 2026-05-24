<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{AuditLog, Comment, DiaryEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
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
        // Admin und User m\u00fcssen in derselben Organisation sein, damit der
        // Admin die Audit-Logs des Users (via OrganizationScope) sehen kann.
        $user = User::factory()->user()->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($user);
        $entry = DiaryEntry::factory()->for($user)->create();
        Comment::factory()->for($user)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id]);

        $this->actingAs($admin)
            ->get(route('audit.index', ['event' => 'created', 'type' => 'comment']))
            ->assertOk()
            ->assertSee('Comment');
    }
}
