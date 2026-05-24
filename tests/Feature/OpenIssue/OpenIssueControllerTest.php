<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\OpenIssue;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueStatus, OpenIssueVisibility};
use App\Models\{DiaryEntry, OpenIssue, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenIssueControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_store_open_issue_against_diary_entry(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.store'), [
                'subject_kind' => 'diary',
                'subject_id' => $entry->id,
                'title' => 'Leitung verlegen',
                'severity' => OpenIssueSeverity::Medium->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('open_issues', [
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'title' => 'Leitung verlegen',
            'severity' => OpenIssueSeverity::Medium->value,
            'status' => OpenIssueStatus::Open->value,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_store(): void {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->post(route('open-issues.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $entry->id,
            'title' => 'X',
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_run_full_lifecycle_via_http(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user);

        $this->from(route('diary.show', $entry))->post(route('open-issues.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $entry->id,
            'title' => 'Lifecycle',
        ])->assertRedirect();

        $issue = OpenIssue::query()->latest('id')->firstOrFail();

        $this->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'start']))
            ->assertRedirect();
        $this->assertSame(OpenIssueStatus::InProgress, $issue->refresh()->status);

        $this->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'complete']), [
                'resolution' => 'fertiggestellt',
            ])
            ->assertRedirect();
        $this->assertSame(OpenIssueStatus::Done, $issue->refresh()->status);
    }

    public function test_complete_requires_resolution_returns_validation_error(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $issue = OpenIssue::factory()
            ->for($entry, 'subject')
            ->state(['created_by_user_id' => $user->id, 'organization_id' => $user->organization_id])
            ->inProgress()
            ->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'complete']), [
                'resolution' => '',
            ])
            ->assertSessionHasErrors('resolution');
    }

    public function test_invalid_transition_returns_status_error(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $issue = OpenIssue::factory()
            ->for($entry, 'subject')
            ->state(['created_by_user_id' => $user->id, 'organization_id' => $user->organization_id])
            ->create();

        // open → block ist nicht erlaubt
        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'block']), [
                'reason' => 'foo',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_non_participant_without_assign_right_cannot_update(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $issue = OpenIssue::factory()
            ->for($entry, 'subject')
            ->state(['created_by_user_id' => $author->id, 'organization_id' => $author->organization_id])
            ->create();

        $this->actingAs($other)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'start']))
            ->assertForbidden();
    }

    public function test_admin_can_act_on_foreign_issue(): void {
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $issue = OpenIssue::factory()
            ->for($entry, 'subject')
            ->state(['created_by_user_id' => $author->id, 'organization_id' => $author->organization_id])
            ->create();

        $this->actingAs($admin)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.transition', ['issue' => $issue, 'action' => 'start']))
            ->assertRedirect();
        $this->assertSame(OpenIssueStatus::InProgress, $issue->refresh()->status);
    }

    public function test_assign_endpoint_requires_assign_permission(): void {
        $author = User::factory()->user()->create();
        $assignee = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $issue = OpenIssue::factory()
            ->for($entry, 'subject')
            ->state(['created_by_user_id' => $author->id, 'organization_id' => $author->organization_id])
            ->create();

        $this->actingAs($author)
            ->from(route('diary.show', $entry))
            ->put(route('open-issues.assign', $issue), [
                'assignee_user_id' => $assignee->id,
            ])
            ->assertForbidden();
    }

    public function test_publishing_to_customer_requires_permission(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('open-issues.store'), [
                'subject_kind' => 'diary',
                'subject_id' => $entry->id,
                'title' => 'Customer-sichtbar',
                'visibility' => OpenIssueVisibility::Customer->value,
            ])
            ->assertForbidden();
    }
}
