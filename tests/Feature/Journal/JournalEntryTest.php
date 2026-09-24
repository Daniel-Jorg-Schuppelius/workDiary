<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JournalEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Journal;

use App\Enums\Disposal\DisposalJobEventType;
use App\Enums\OpenIssue\OpenIssueEventType;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Finance\BillingTransfer;
use App\Models\Journal\JournalEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Whistleblowing\CaseEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Journal-Baustein (MVP-864): record()/log(), Spaltenabbildung, Label, Append-only. */
class JournalEntryTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->app->instance('currentOrganization', $this->organization);
    }

    public function test_record_writes_subject_actor_payload_and_time(): void {
        $transfer = BillingTransfer::factory()->create(['organization_id' => $this->organization->id]);

        $entry = $transfer->record('position_edited', ['position' => 3], $this->user);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertSame((int) $transfer->id, (int) $entry->getAttribute('billing_transfer_id'));
        $this->assertTrue($entry->subject()->is($transfer));
        $this->assertSame((int) $this->organization->id, (int) $entry->getAttribute('organization_id'));
        $this->assertSame($this->user->id, $entry->actor?->id);
        $this->assertSame(['position' => 3], $entry->payloadData());
        $this->assertSame('position_edited', $entry->eventKey());
        $this->assertNotNull($entry->occurredAt());
        $this->assertNotNull($entry->getAttribute('hash'), 'Hash-Kette läuft weiter durch den Baustein.');
        $this->assertCount(1, $transfer->journal()->get());
    }

    public function test_column_mapping_serves_metadata_journals(): void {
        $entry = CaseEvent::log(null, 'opened', ['channel' => 'mail'], $this->user, extra: ['organization_id' => $this->organization->id, 'actor_type' => 'system']);

        $this->assertSame(['channel' => 'mail'], $entry->getAttribute('metadata'));
        $this->assertSame(['channel' => 'mail'], $entry->payloadData());
        $this->assertSame('system', $entry->getAttribute('actor_type'));
        $this->assertSame('metadata', CaseEvent::column('payload'));
        $this->assertSame('created_at', CaseEvent::column('occurred_at'));
        $this->assertSame(__('journal.whistleblowing.opened'), $entry->label());
    }

    public function test_label_prefers_enum_then_translation_then_headline(): void {
        $job = \App\Models\Disposal\DisposalJob::factory()->create(['organization_id' => $this->organization->id]);
        $entry = $job->record(DisposalJobEventType::cases()[0], [], $this->user);
        $this->assertSame(DisposalJobEventType::cases()[0]->label(), $entry->label());

        $transfer = BillingTransfer::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertSame(__('finance.event.transferred'), $transfer->record('transferred', [], $this->user)->label());
        $this->assertSame('Something Odd', $transfer->record('something.odd', [], $this->user)->label());
    }

    public function test_journal_entries_are_append_only(): void {
        $issue = OpenIssue::factory()->create(['organization_id' => $this->organization->id]);
        $entry = $issue->record(OpenIssueEventType::Created, ['via' => 'test'], $this->user);

        $this->assertSame(OpenIssueEventType::Created->value, $entry->eventKey());
        $this->assertSame(__('journal.diary.issue.created'), $entry->label());
        $this->expectException(\RuntimeException::class);
        $entry->update(['payload' => ['via' => 'geändert']]);
    }

    public function test_diary_lifecycle_uses_occurred_at(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id]);
        $count = $entry->journal()->count();
        $entry->record('order.pause', [], $this->user, extra: ['from_status' => 'in_progress', 'to_status' => 'paused', 'actor_kind' => 'user']);

        $this->assertSame($count + 1, $entry->journal()->count());
        $this->assertSame('occurred_at', \App\Models\Diary\DiaryEntryEvent::column('occurred_at'));
    }
}
