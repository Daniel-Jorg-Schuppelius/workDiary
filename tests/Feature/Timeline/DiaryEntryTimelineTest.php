<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryTimelineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Timeline;

use App\Enums\Diary\Status;
use App\Enums\OpenIssue\OpenIssueEventType;
use App\Enums\Protocol\ProtocolEventType;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{CommunicationNote, DiaryEntry, Document, MaterialUsage, OpenIssue, OpenIssueEvent, Project, Protocol, ProtocolEvent, TimeEntry, Timesheet, User};
use App\Services\Timeline\DiaryEntryTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryEntryTimelineTest extends TestCase {
    use RefreshDatabase;

    private function service(): DiaryEntryTimelineService {
        return app(DiaryEntryTimelineService::class);
    }

    /**
     * Direkte Service-Aufrufe laufen außerhalb des HTTP-Stacks — dort setzt
     * sonst SetOrganizationContext den Org-/Permission-Team-Kontext. Hier
     * spiegeln wir die Middleware, damit Gates + Feature-Flags greifen.
     */
    private function actingAsWithContext(User $user): void {
        $this->actingAs($user);
        app()->instance('currentOrganization', $user->organization()->firstOrFail());
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId((int) $user->organization_id);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();
    }

    /**
     * Baut einen Auftrag mit Ereignissen aus allen Timeline-Quellen.
     */
    private function makeEntryWithAllSources(User $user): DiaryEntry {
        $orgId = (int) $user->organization_id;

        /** @var DiaryEntry $entry */
        $entry = DiaryEntry::factory()->for($user)->create(['organization_id' => $orgId]);

        // Statuswechsel → Auditable-Trait schreibt updated mit before/after.status
        $entry->update(['status' => Status::InProgress->value]);

        // Zeitbuchung mit Timesheet (für die Materialbuchung)
        $project = Project::factory()->create(['organization_id' => $orgId]);
        $timesheet = Timesheet::create([
            'organization_id' => $orgId,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'work_date' => '2030-02-15',
            'status' => TimesheetStatus::Draft->value,
        ]);
        TimeEntry::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'diary_entry_id' => $entry->id,
            'timesheet_id' => $timesheet->id,
            'minutes' => 90,
            'description' => 'Wartung vor Ort',
        ]);

        MaterialUsage::create([
            'organization_id' => $orgId,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kupferrohr 15mm',
            'quantity' => '2.5',
            'unit' => 'm',
        ]);

        $entry->comments()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'body' => 'Rücksprache mit dem Kunden gehalten.',
        ]);

        $entry->attachments()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => 'attachments/test.pdf',
            'original_name' => 'messprotokoll.pdf',
            'mime' => 'application/pdf',
            'size' => 1234,
        ]);

        $protocol = Protocol::factory()->create([
            'organization_id' => $orgId,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Abnahmeprotokoll Heizung',
        ]);
        ProtocolEvent::create([
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::Created,
            'actor_user_id' => $user->id,
            'created_at' => now(),
        ]);

        $issue = OpenIssue::factory()->create([
            'organization_id' => $orgId,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Dichtung nachbessern',
        ]);
        OpenIssueEvent::create([
            'open_issue_id' => $issue->id,
            'event' => OpenIssueEventType::Created,
            'actor_user_id' => $user->id,
            'created_at' => now(),
        ]);

        CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $orgId,
            'created_by_user_id' => $user->id,
            'subject' => 'Telefonat Terminabstimmung',
        ]);

        Document::factory()->create([
            'organization_id' => $orgId,
            'documentable_type' => DiaryEntry::class,
            'documentable_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Wartungsvertrag',
        ]);

        return $entry;
    }

    public function test_timeline_contains_events_from_all_sources_in_descending_order(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeEntryWithAllSources($user);

        $this->actingAsWithContext($user);
        $result = $this->service()->forDiaryEntry($entry, $user);
        $items = $result['items'];

        $ids = array_map(fn($item) => $item->id, $items);
        $prefixes = array_unique(array_map(fn(string $id) => explode(':', $id)[0], $ids));

        foreach (['audit', 'time', 'comment', 'attachment', 'protocol-event', 'material', 'issue-event', 'communication', 'document'] as $expected) {
            $this->assertContains($expected, $prefixes, "Quelle '$expected' fehlt in der Timeline.");
        }

        // Statuswechsel-Item (updated mit before/after.status) ist enthalten
        $titles = array_map(fn($item) => $item->title, $items);
        $this->assertContains(__('timeline.event.status_changed'), $titles);
        $this->assertContains(__('timeline.event.order_created'), $titles);

        // chronologisch absteigend
        $timestamps = array_map(fn($item) => $item->occurredAt?->getTimestamp() ?? PHP_INT_MIN, $items);
        $sorted = $timestamps;
        rsort($sorted);
        $this->assertSame($sorted, $timestamps, 'Timeline ist nicht absteigend sortiert.');
    }

    public function test_confidential_note_is_hidden_from_other_users_but_visible_to_creator(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();

        CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
            'subject' => 'Vertrauliche Preisabsprache',
        ]);

        $this->actingAsWithContext($author);
        $forAuthor = $this->service()->forDiaryEntry($entry, $author, ['communication']);
        $this->assertCount(1, $forAuthor['items'], 'Ersteller muss die vertrauliche Notiz sehen.');

        $this->actingAsWithContext($other);
        $forOther = $this->service()->forDiaryEntry($entry, $other, ['communication']);
        $this->assertCount(0, $forOther['items'], 'Unberechtigte dürfen die vertrauliche Notiz nicht sehen.');
    }

    public function test_type_filter_limits_timeline_to_selected_source(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeEntryWithAllSources($user);

        $this->actingAsWithContext($user);
        $result = $this->service()->forDiaryEntry($entry, $user, ['comment']);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertSame('comment', $item->type);
        }
    }

    public function test_show_page_renders_timeline_section_and_respects_type_filter(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeEntryWithAllSources($user);

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('id="timeline"', false)
            ->assertSee(__('timeline.event.time_entry_added'))
            ->assertSee(__('timeline.event.comment_added'));

        // Serverseitiger Typ-Filter: nur Kommentare
        $this->actingAs($user)
            ->get(route('diary.show', [$entry, 'timeline_type' => 'comment']))
            ->assertOk()
            ->assertSee(__('timeline.event.comment_added'))
            ->assertDontSee(__('timeline.event.time_entry_added'));
    }

    public function test_load_more_grows_the_visible_window(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        for ($i = 0; $i < 60; $i++) {
            $entry->comments()->create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'body' => 'Kommentar ' . $i,
            ]);
        }

        $this->actingAsWithContext($user);
        $page1 = $this->service()->forDiaryEntry($entry, $user, ['comment'], 50);
        $this->assertCount(50, $page1['items']);
        $this->assertTrue($page1['hasMore']);

        $page2 = $this->service()->forDiaryEntry($entry, $user, ['comment'], 100);
        // 60 Kommentare + nichts anderes
        $this->assertCount(60, $page2['items']);
        $this->assertFalse($page2['hasMore']);
    }

    public function test_customer_timeline_aggregates_orders_notes_and_documents(): void {
        $user = User::factory()->user()->create();
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $user->organization_id]);

        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'status' => Status::Done->value,
            'title' => 'Heizungswartung Q1',
        ]);

        CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'subject' => 'Terminbestätigung',
        ]);
        Document::factory()->create([
            'organization_id' => $user->organization_id,
            'documentable_type' => \App\Models\Customer::class,
            'documentable_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'title' => 'Rahmenvertrag',
        ]);

        $this->actingAsWithContext($user);
        $result = $this->service()->forCustomer($customer, $user);

        $titles = array_map(fn($item) => $item->title, $result['items']);
        $this->assertContains(__('timeline.event.order_created'), $titles);
        $this->assertContains(__('timeline.event.order_completed'), $titles);
        $this->assertContains(__('timeline.event.communication_added'), $titles);
        $this->assertContains(__('timeline.event.document_linked'), $titles);

        // Kunden-Detailseite rendert die Verlaufs-Karte
        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('id="customer-timeline"', false);
    }

    public function test_procedure_run_appears_in_order_timeline(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $run = \App\Models\ProcedureRun::factory()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'status' => \App\Enums\Procedure\ProcedureRunStatus::Completed->value,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAsWithContext($user);
        $result = $this->service()->forDiaryEntry($entry, $user, ['procedure']);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('procedure-run:' . $run->id, $item->id);
        $this->assertSame('procedure', $item->type);
        $this->assertSame(__('timeline.event.procedure_run_completed'), $item->title);

        // Lauf eines fremden Auftrags erscheint nicht.
        $other = DiaryEntry::factory()->for($user)->create();
        $all = $this->service()->forDiaryEntry($other, $user, ['procedure']);
        $this->assertCount(0, $all['items']);
    }

    public function test_customer_timeline_mixes_all_sources_and_respects_type_filter(): void {
        $admin = User::factory()->admin()->create();
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $admin->organization_id]);

        $entry = DiaryEntry::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
            'customer_id' => $customer->id,
            'status' => Status::Done->value,
            'title' => 'Wartungsvertrag Q3',
        ]);

        $protocol = Protocol::factory()->create([
            'organization_id' => $admin->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Abnahme Wartung',
        ]);
        ProtocolEvent::create([
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::Created,
            'actor_user_id' => $admin->id,
            'created_at' => now(),
        ]);

        \App\Models\Invoice::query()->create([
            'organization_id' => $admin->organization_id,
            'customer_id' => $customer->id,
            'number' => 'RE-2026-042',
            'status' => 'issued',
            'type' => 'invoice',
            'issued_on' => '2026-07-01',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_rate' => 19.00,
            'tax_amount' => 19.00,
            'total' => 119.00,
        ]);

        \App\Models\Quote::query()->create([
            'organization_id' => $admin->organization_id,
            'customer_id' => $customer->id,
            'number' => 'AN-2026-007',
            'status' => 'sent',
            'created_by' => $admin->id,
        ]);

        $this->actingAsWithContext($admin);
        $result = $this->service()->forCustomer($customer, $admin);
        $items = $result['items'];

        $titles = array_map(fn($item) => $item->title, $items);
        foreach ([
            __('timeline.event.order_created'),
            __('timeline.event.order_completed'),
            __('timeline.event.protocol.created'),
            __('timeline.event.invoice_issued'),
            __('timeline.event.quote_created'),
        ] as $expected) {
            $this->assertContains($expected, $titles, "Ereignis '$expected' fehlt in der Kunden-Timeline.");
        }

        // chronologisch absteigend gemischt
        $timestamps = array_map(fn($item) => $item->occurredAt?->getTimestamp() ?? PHP_INT_MIN, $items);
        $sorted = $timestamps;
        rsort($sorted);
        $this->assertSame($sorted, $timestamps, 'Kunden-Timeline ist nicht absteigend sortiert.');

        // Serverseitiger Typ-Filter: nur Rechnungen
        $filtered = $this->service()->forCustomer($customer, $admin, ['invoice']);
        $this->assertNotEmpty($filtered['items']);
        foreach ($filtered['items'] as $item) {
            $this->assertSame('invoice', $item->type);
        }

        // Kunden-Detailseite rendert Filter-Chips der vollwertigen Timeline
        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('id="customer-timeline"', false)
            ->assertSee(__('timeline.filter.invoice'));
    }

    public function test_customer_timeline_is_org_isolated(): void {
        $userA = User::factory()->admin()->create();
        $customerA = \App\Models\Customer::factory()->create(['organization_id' => $userA->organization_id]);
        DiaryEntry::factory()->for($userA)->create([
            'organization_id' => $userA->organization_id,
            'customer_id' => $customerA->id,
            'title' => 'Auftrag Org A',
        ]);

        $userB = User::factory()->admin()->create();
        $customerB = \App\Models\Customer::factory()->create(['organization_id' => $userB->organization_id]);
        DiaryEntry::factory()->for($userB)->create([
            'organization_id' => $userB->organization_id,
            'customer_id' => $customerB->id,
            'title' => 'Auftrag Org B',
        ]);

        $this->actingAsWithContext($userA);
        $result = $this->service()->forCustomer($customerA, $userA);

        $summaries = array_map(fn($item) => (string) $item->summary, $result['items']);
        $this->assertContains('Auftrag Org A', $summaries);
        $this->assertNotContains('Auftrag Org B', $summaries);
    }
}
