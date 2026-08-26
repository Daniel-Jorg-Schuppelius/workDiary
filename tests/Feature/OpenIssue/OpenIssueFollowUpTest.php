<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueFollowUpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\OpenIssue;

use App\Enums\Diary\{LocationMode, Mode};
use App\Enums\Project\ProjectStatus;
use App\Models\{AuditLog, Customer, DiaryEntry, OpenIssue, Organization, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Folgeauftrag aus offenem Punkt (Feature 139, MVP-704): Dialog-Prefill aus
 * dem Punkt, Rückverknüpfung + Audit beim Speichern, Mandanten- und
 * Rechtegrenze, Aktion/Link im Punkte-Panel.
 */
class OpenIssueFollowUpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'created_by' => $this->user->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Sanierung',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    private function issueOnDiary(?User $creator = null): OpenIssue {
        $entry = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ]);

        return OpenIssue::factory()->for($entry, 'subject')->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => ($creator ?? $this->user)->id,
            'title' => 'Fenster klemmt',
            'description' => 'Rahmen prüfen und nachstellen.',
        ]);
    }

    /** @return array<string, mixed> */
    private function storePayload(OpenIssue $issue): array {
        return [
            'content' => 'Folgeauftrag zu offenem Punkt #' . $issue->id,
            'title' => $issue->title,
            'status' => 2,
            'mode' => Mode::Fixed->value,
            'location_mode' => LocationMode::Onsite->value,
            'start_at' => '2030-03-01 09:00:00',
            'end_at' => '2030-03-01 10:00:00',
            'entry_type_id' => '0',
            'customer_id' => $this->customer->sqid,
            'project_id' => $this->project->sqid,
            'open_issue_id' => $issue->sqid,
        ];
    }

    public function test_create_dialog_is_prefilled_from_open_issue(): void {
        $issue = $this->issueOnDiary();

        $response = $this->actingAs($this->user)
            ->get(route('diary.create', ['open_issue' => $issue->sqid]))
            ->assertOk();

        $html = (string) $response->getContent();
        $this->assertStringContainsString('value="Fenster klemmt"', $html);
        $this->assertStringContainsString('Folgeauftrag zu offenem Punkt #' . $issue->id, $html);
        $this->assertStringContainsString('Rahmen prüfen und nachstellen.', $html);
        $this->assertStringContainsString('name="open_issue_id" value="' . $issue->sqid . '"', $html);
        $this->assertStringContainsString('name="project_id" value="' . $this->project->sqid . '"', $html);
        $this->assertStringContainsString('value="' . $this->customer->sqid . '" selected', $html);
    }

    public function test_generic_prefill_parameters_are_understood(): void {
        $response = $this->actingAs($this->user)
            ->get(route('diary.create', [
                'customer_id' => $this->customer->sqid,
                'title' => 'Nachbesserung',
                'content' => 'Freitext-Prefill',
            ]))
            ->assertOk();

        $html = (string) $response->getContent();
        $this->assertStringContainsString('value="Nachbesserung"', $html);
        $this->assertStringContainsString('Freitext-Prefill', $html);
        $this->assertStringContainsString('value="' . $this->customer->sqid . '" selected', $html);
        // Ohne Punkt keine Rückverknüpfung.
        $this->assertStringNotContainsString('name="open_issue_id"', $html);
    }

    public function test_store_links_follow_up_and_writes_audit(): void {
        $issue = $this->issueOnDiary();

        $this->actingAs($this->user)
            ->post(route('diary.store'), $this->storePayload($issue))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $entry = DiaryEntry::query()->where('title', 'Fenster klemmt')->firstOrFail();
        $this->assertSame($this->customer->id, (int) $entry->customer_id);
        $this->assertSame($this->project->id, (int) $entry->project_id);
        $this->assertSame((int) $entry->id, (int) $issue->fresh()?->follow_up_diary_entry_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'openIssue.followUpCreated',
            'auditable_type' => OpenIssue::class,
            'auditable_id' => $issue->id,
        ]);
        $log = AuditLog::query()->where('event', 'openIssue.followUpCreated')->firstOrFail();
        $this->assertSame((int) $entry->id, (int) data_get($log->changes, 'diary_entry_id'));
    }

    public function test_foreign_organization_issue_is_not_reachable(): void {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $otherEntry = DiaryEntry::factory()->for($otherUser)->create(['organization_id' => $otherOrg->id]);
        $foreign = OpenIssue::factory()->for($otherEntry, 'subject')->create([
            'organization_id' => $otherOrg->id,
            'created_by_user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('diary.create', ['open_issue' => $foreign->sqid]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->post(route('diary.store'), $this->storePayload($foreign))
            ->assertSessionHasErrors('open_issue_id');

        $this->assertNull($foreign->fresh()?->follow_up_diary_entry_id);
    }

    public function test_requires_update_right_on_issue(): void {
        // Weder Ersteller noch Zugewiesener, ohne openIssue.assign → kein update.
        $creator = $this->orgUser();
        $issue = $this->issueOnDiary($creator);

        $this->actingAs($this->user)
            ->get(route('diary.create', ['open_issue' => $issue->sqid]))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('diary.store'), $this->storePayload($issue))
            ->assertForbidden();

        $this->assertNull($issue->fresh()?->follow_up_diary_entry_id);
        $this->assertSame(0, DiaryEntry::query()->where('title', 'Fenster klemmt')->count());
    }

    public function test_panel_offers_action_and_shows_link_after_linking(): void {
        $issue = $this->issueOnDiary();
        /** @var DiaryEntry $subject */
        $subject = $issue->subject;

        $this->actingAs($this->user)
            ->get(route('diary.show', $subject))
            ->assertOk()
            ->assertSee('open_issue=' . $issue->sqid, false)
            ->assertSee(__('open-issue.action.followUp'));

        $followUp = DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'title' => 'Nachbesserung Fenster',
        ]);
        $issue->update(['follow_up_diary_entry_id' => $followUp->id]);

        $this->actingAs($this->user)
            ->get(route('diary.show', $subject))
            ->assertOk()
            ->assertSee(route('diary.show', $followUp), false)
            ->assertSee('Nachbesserung Fenster')
            ->assertDontSee('open_issue=' . $issue->sqid, false);
    }
}
