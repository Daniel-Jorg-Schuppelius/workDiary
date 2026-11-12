<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueCompletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\OpenIssue;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueStatus, OpenIssueVisibility};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** MVP-1597: Frist, Schwere, Sichtbarkeit ändern; Punkte an Projekt, Kunde, Asset; Kommentare, Anhänge, Filter. */
class OpenIssueCompletionTest extends TestCase {
    use RefreshDatabase;

    private User $author;

    private DiaryEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->author = User::factory()->user()->create();
        $this->entry = DiaryEntry::factory()->for($this->author)->create();
    }

    /** @param array<string, mixed> $state */
    private function issue(array $state = []): OpenIssue {
        return OpenIssue::factory()->for($this->entry, 'subject')->create($state + [
            'organization_id' => $this->author->organization_id,
            'created_by_user_id' => $this->author->id,
            'status' => OpenIssueStatus::Open->value,
            'severity' => OpenIssueSeverity::Low->value,
            'visibility' => OpenIssueVisibility::Internal->value,
        ]);
    }

    public function test_due_date_and_severity_change_through_the_edit_dialog_and_land_in_the_journal(): void {
        $issue = $this->issue();

        $this->actingAs($this->author)->get(route('open-issues.edit', $issue) . '?dialog=1')
            ->assertOk()
            ->assertSee('name="severity"', false)
            ->assertSee('name="due_at"', false)
            ->assertDontSee('name="visibility"', false);

        $this->from(route('diary.show', $this->entry))
            ->put(route('open-issues.update', $issue), ['title' => $issue->title, 'severity' => 'high', 'due_at' => '2031-05-20T10:00'])
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame(OpenIssueSeverity::High, $issue->severity);
        $this->assertSame('2031-05-20', $issue->due_at->toDateString());
        $events = $issue->journal()->pluck('event')->map(fn ($event) => $event->value)->all();
        $this->assertContains('issue.dueDateChanged', $events);
        $this->assertContains('issue.severityChanged', $events);

        // Unverändert gespeichert: kein weiteres Ereignis.
        $count = $issue->journal()->count();
        $this->put(route('open-issues.update', $issue), ['title' => $issue->title, 'severity' => 'high', 'due_at' => '2031-05-20T10:00'])->assertRedirect();
        $this->assertSame($count, $issue->journal()->count());
    }

    public function test_critical_without_due_date_gets_the_default_deadline(): void {
        $issue = $this->issue();

        $this->actingAs($this->author)
            ->put(route('open-issues.update', $issue), ['title' => $issue->title, 'severity' => 'critical', 'due_at' => ''])
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame(OpenIssueSeverity::Critical, $issue->severity);
        $this->assertNotNull($issue->due_at);
        $this->assertSame($this->author->id, (int) $issue->assignee_user_id);
    }

    public function test_customer_visibility_needs_the_publish_permission(): void {
        $issue = $this->issue();

        $this->actingAs($this->author)
            ->put(route('open-issues.update', $issue), ['title' => $issue->title, 'visibility' => 'customer'])
            ->assertForbidden();
        $this->assertSame(OpenIssueVisibility::Internal, $issue->refresh()->visibility);

        $admin = User::factory()->admin()->create(['organization_id' => $this->author->organization_id]);
        $this->actingAs($admin)->get(route('open-issues.edit', $issue) . '?dialog=1')->assertOk()->assertSee('name="visibility"', false);
        $this->put(route('open-issues.update', $issue), ['title' => $issue->title, 'visibility' => 'customer'])->assertRedirect();

        $issue->refresh();
        $this->assertSame(OpenIssueVisibility::Customer, $issue->visibility);
        $this->assertTrue($issue->journal()->where('event', 'issue.visibilityChanged')->exists());
    }

    public function test_assignee_can_be_chosen_at_creation(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->author->organization_id]);
        $colleague = User::factory()->user()->create(['organization_id' => $this->author->organization_id, 'name' => 'Kim Beispiel']);
        $subject = Sqid::encode(DiaryEntry::class, $this->entry->id);

        $this->actingAs($admin)->get(route('open-issues.create', ['subject_kind' => 'diary', 'subject_id' => $subject]))
            ->assertOk()
            ->assertSee('name="assignee_user_id"', false)
            ->assertSee('Kim Beispiel');
        $this->from(route('diary.show', $this->entry))->post(route('open-issues.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $subject,
            'title' => 'Dichtung tauschen',
            'assignee_user_id' => $colleague->sqid,
        ])->assertRedirect();

        $this->assertDatabaseHas('open_issues', ['title' => 'Dichtung tauschen', 'assignee_user_id' => $colleague->id]);

        // Ohne Zuweisungsrecht fehlt das Feld.
        $this->actingAs($this->author)->get(route('open-issues.create', ['subject_kind' => 'diary', 'subject_id' => $subject]))
            ->assertOk()
            ->assertDontSee('name="assignee_user_id"', false);
    }

    public function test_issues_can_be_created_and_shown_at_project_customer_and_asset(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->author->organization_id]);
        $orgId = $admin->organization_id;
        $customer = Customer::factory()->create(['organization_id' => $orgId]);
        $project = Project::factory()->create(['organization_id' => $orgId, 'customer_id' => $customer->id]);
        $asset = Asset::factory()->create(['organization_id' => $orgId]);
        $this->actingAs($admin);

        foreach (['customer' => $customer, 'project' => $project, 'asset' => $asset] as $kind => $subject) {
            $this->post(route('open-issues.store'), [
                'subject_kind' => $kind,
                'subject_id' => Sqid::encode($subject::class, $subject->id),
                'title' => 'Punkt am ' . $kind,
            ])->assertRedirect();

            $this->assertDatabaseHas('open_issues', [
                'subject_type' => MorphMap::alias($subject::class),
                'subject_id' => $subject->id,
                'title' => 'Punkt am ' . $kind,
            ]);
            $this->assertSame(1, $subject->openIssues()->count());
        }

        $this->get(route('customers.show', $customer))->assertOk()->assertSee('Punkt am customer')->assertSee('id="open-issues"', false);
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Punkt am asset');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('Punkt am project');
    }

    public function test_comment_is_stored_and_recorded_in_the_journal(): void {
        $issue = $this->issue();

        $this->actingAs($this->author)
            ->from(route('diary.show', $this->entry))
            ->post(route('open-issues.comments.store', $issue), ['body' => 'Ersatzteil ist bestellt.'])
            ->assertRedirect();

        $this->assertSame('Ersatzteil ist bestellt.', $issue->comments()->firstOrFail()->body);
        $this->assertTrue($issue->journal()->where('event', 'issue.commentAdded')->exists());

        $this->get(route('open-issues.show', $issue) . '?dialog=1')
            ->assertOk()
            ->assertSee('Ersatzteil ist bestellt.')
            ->assertSee(__('journal.diary.issue.commentAdded'));
    }

    public function test_attachment_follows_the_issue_and_is_locked_once_closed(): void {
        Storage::fake('local');
        Storage::fake(config('filesystems.default'));
        $issue = $this->issue();
        $this->actingAs($this->author);

        $this->from(route('diary.show', $this->entry))
            ->post(route('attachments.store', ['type' => 'open-issue', 'id' => $issue->sqid]), ['file' => UploadedFile::fake()->create('foto.pdf', 20, 'application/pdf')])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $issue->attachments()->count());
        $this->assertTrue($issue->journal()->where('event', 'issue.attachmentAdded')->exists());

        $issue->forceFill(['status' => OpenIssueStatus::Done->value, 'closed_at' => now()])->save();
        $this->post(route('attachments.store', ['type' => 'open-issue', 'id' => $issue->sqid]), ['file' => UploadedFile::fake()->create('nach.pdf', 20, 'application/pdf')])
            ->assertForbidden();
        $this->delete(route('attachments.destroy', $issue->attachments()->firstOrFail()))->assertForbidden();
    }

    public function test_detail_dialog_is_closed_to_users_without_view_right(): void {
        $issue = $this->issue();
        $stranger = User::factory()->user()->create();

        $this->actingAs($stranger)->get(route('open-issues.show', $issue) . '?dialog=1')->assertStatus(404);
    }

    public function test_panel_filters_by_status_severity_and_assignee(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->author->organization_id]);
        $this->issue(['title' => 'Offen niedrig']);
        $this->issue(['title' => 'Blockiert hoch', 'status' => OpenIssueStatus::Blocked->value, 'severity' => OpenIssueSeverity::High->value, 'assignee_user_id' => $colleague->id]);
        $this->actingAs($this->author);

        $this->get(route('diary.show', [$this->entry, 'issue_status' => 'blocked']))
            ->assertOk()->assertSee('Blockiert hoch')->assertDontSee('Offen niedrig');
        $this->get(route('diary.show', [$this->entry, 'issue_severity' => 'low']))
            ->assertOk()->assertSee('Offen niedrig')->assertDontSee('Blockiert hoch');
        $this->get(route('diary.show', [$this->entry, 'issue_assignee' => $colleague->sqid]))
            ->assertOk()->assertSee('Blockiert hoch')->assertDontSee('Offen niedrig');
        $this->get(route('diary.show', [$this->entry, 'issue_assignee' => 'none']))
            ->assertOk()->assertSee('Offen niedrig')->assertDontSee('Blockiert hoch');
        $this->get(route('diary.show', [$this->entry, 'issue_status' => 'done']))
            ->assertOk()->assertSee(__('open-issue.filter.no_match'));
    }
}
