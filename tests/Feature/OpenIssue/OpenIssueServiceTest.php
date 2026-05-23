<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\OpenIssue;

use App\Enums\OpenIssue\OpenIssueEventType;
use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\OpenIssue\OpenIssueVisibility;
use App\Exceptions\InvalidOpenIssueTransitionException;
use App\Models\DiaryEntry;
use App\Models\OpenIssue;
use App\Models\User;
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OpenIssueServiceTest extends TestCase {
    use RefreshDatabase;

    private OpenIssueService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(OpenIssueService::class);
    }

    public function test_create_low_issue_records_created_event(): void {
        [$creator, $entry] = $this->makeContext();

        $issue = $this->service->create($entry, $creator, [
            'title' => 'Geländer fehlt',
            'description' => 'Treppe 2. OG, geländerlos',
            'severity' => OpenIssueSeverity::Low->value,
        ]);

        $this->assertSame(OpenIssueStatus::Open, $issue->status);
        $this->assertSame(OpenIssueSeverity::Low, $issue->severity);
        $this->assertSame($creator->id, $issue->created_by_user_id);
        $this->assertNull($issue->assignee_user_id);
        $this->assertNull($issue->due_at);
        $this->assertSame(OpenIssueVisibility::Internal, $issue->visibility);

        $this->assertDatabaseHas('open_issue_events', [
            'open_issue_id' => $issue->id,
            'event' => OpenIssueEventType::Created->value,
        ]);
    }

    public function test_create_critical_issue_defaults_due_date_and_assignee(): void {
        [$creator, $entry] = $this->makeContext();

        $issue = $this->service->create($entry, $creator, [
            'title' => 'Kritisch',
            'severity' => OpenIssueSeverity::Critical->value,
        ]);

        $this->assertNotNull($issue->due_at, 'Critical-Issue muss Default-Frist erhalten');
        $this->assertSame($creator->id, $issue->assignee_user_id);

        $this->assertDatabaseHas('open_issue_events', [
            'open_issue_id' => $issue->id,
            'event' => OpenIssueEventType::Assigned->value,
        ]);
    }

    public function test_full_lifecycle_open_inprogress_done(): void {
        [$creator, $entry] = $this->makeContext();

        $issue = $this->service->create($entry, $creator, [
            'title' => 'Lifecycle',
        ]);

        $this->service->start($issue, $creator);
        $this->assertSame(OpenIssueStatus::InProgress, $issue->refresh()->status);

        $this->service->complete($issue, $creator, 'erledigt am Nachmittag');
        $this->assertSame(OpenIssueStatus::Done, $issue->refresh()->status);
        $this->assertNotNull($issue->closed_at);
        $this->assertSame($creator->id, $issue->closed_by_user_id);
        $this->assertSame('erledigt am Nachmittag', $issue->closed_reason);
    }

    public function test_block_requires_reason(): void {
        [$creator, $entry] = $this->makeContext();
        $issue = $this->service->create($entry, $creator, ['title' => 'X']);
        $this->service->start($issue, $creator);

        $this->expectException(InvalidArgumentException::class);
        $this->service->block($issue, $creator, '   ');
    }

    public function test_complete_requires_resolution(): void {
        [$creator, $entry] = $this->makeContext();
        $issue = $this->service->create($entry, $creator, ['title' => 'X']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->complete($issue, $creator, '');
    }

    public function test_invalid_transition_throws(): void {
        [$creator, $entry] = $this->makeContext();
        $issue = $this->service->create($entry, $creator, ['title' => 'X']);

        // open → blocked ist nicht erlaubt (muss erst durch inProgress)
        $this->expectException(InvalidOpenIssueTransitionException::class);
        $this->service->block($issue, $creator, 'because');
    }

    public function test_block_unblock_cycle(): void {
        [$creator, $entry] = $this->makeContext();
        $issue = $this->service->create($entry, $creator, ['title' => 'X']);
        $this->service->start($issue, $creator);
        $this->service->block($issue, $creator, 'Material fehlt');
        $this->assertSame(OpenIssueStatus::Blocked, $issue->refresh()->status);

        $this->service->unblock($issue, $creator);
        $this->assertSame(OpenIssueStatus::InProgress, $issue->refresh()->status);
    }

    public function test_reopen_after_done_clears_closed_metadata(): void {
        [$creator, $entry] = $this->makeContext();
        $issue = $this->service->create($entry, $creator, ['title' => 'X']);
        $this->service->complete($issue, $creator, 'fertig');

        $this->service->reopen($issue, $creator, 'Reklamation');
        $issue->refresh();
        $this->assertSame(OpenIssueStatus::Reopened, $issue->status);
        $this->assertNull($issue->closed_at);
        $this->assertNull($issue->closed_by_user_id);
        $this->assertNull($issue->closed_reason);
    }

    public function test_assign_logs_event_and_updates_field(): void {
        [$creator, $entry] = $this->makeContext();
        $assignee = User::factory()->user()->create([
            'organization_id' => $creator->organization_id,
        ]);

        $issue = $this->service->create($entry, $creator, ['title' => 'X']);
        $this->service->assign($issue, $assignee, $creator);

        $this->assertSame($assignee->id, $issue->refresh()->assignee_user_id);
        $this->assertDatabaseHas('open_issue_events', [
            'open_issue_id' => $issue->id,
            'event' => OpenIssueEventType::Assigned->value,
        ]);
    }

    /**
     * @return array{0: User, 1: DiaryEntry}
     */
    private function makeContext(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();

        return [$creator, $entry];
    }
}
