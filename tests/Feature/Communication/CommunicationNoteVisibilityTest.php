<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteVisibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Communication;

use App\Models\{CommunicationNote, DiaryEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationNoteVisibilityTest extends TestCase {
    use RefreshDatabase;

    public function test_confidential_note_is_hidden_from_other_users_via_scope(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();

        CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);
        $confidential = CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $visibleToOther = CommunicationNote::query()->visibleTo($other)->pluck('id');
        $this->assertCount(1, $visibleToOther);
        $this->assertNotContains($confidential->id, $visibleToOther->all());

        $visibleToAuthor = CommunicationNote::query()->visibleTo($author)->pluck('id');
        $this->assertCount(2, $visibleToAuthor);
    }

    public function test_admin_sees_confidential_notes_via_scope(): void {
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();

        CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->assertCount(1, CommunicationNote::query()->visibleTo($admin)->get());
    }

    public function test_policy_denies_view_of_confidential_note_to_third_users(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->assertTrue($author->can('view', $note), 'Erfasser sieht die eigene vertrauliche Notiz');
        $this->assertFalse($other->can('view', $note), 'Dritte ohne confidential.manage sehen die Notiz nicht');
        $this->assertTrue($admin->can('view', $note), 'Org-Admin sieht vertrauliche Notizen');
    }

    public function test_policy_allows_view_of_normal_note_to_org_members(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->assertTrue($other->can('view', $note));
    }

    public function test_marking_confidential_forces_internal_visibility_and_audits(): void {
        $admin = User::factory()->admin()->create();
        $entry = DiaryEntry::factory()->for($admin)->create();
        $note = CommunicationNote::factory()->customerVisible()->for($entry, 'notable')->create([
            'organization_id' => $admin->organization_id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.confidential', $note), ['confidential' => 1])
            ->assertRedirect();

        $note->refresh();
        $this->assertTrue($note->confidential);
        $this->assertSame('internal', $note->visibility->value);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'communication.confidential.set',
            'auditable_type' => CommunicationNote::class,
            'auditable_id' => $note->id,
        ]);
    }

    public function test_non_admin_cannot_toggle_confidential(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('communication-notes.confidential', $note), ['confidential' => 1])
            ->assertForbidden();
    }
}
