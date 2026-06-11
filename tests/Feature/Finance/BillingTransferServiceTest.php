<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, MaterialUsage, Project, TimeEntry, Timesheet, User};
use App\Models\Finance\BillingTransferEvent;
use App\Services\Finance\{BillingTransferException, BillingTransferService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class BillingTransferServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    private BillingTransferService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $this->service = app(BillingTransferService::class);
    }

    private function makeTimeEntry(array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ], $overrides));
    }

    private function makeMaterialUsage(string $workDate = '2030-04-02'): MaterialUsage {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $this->project->id,
            'work_date' => $workDate,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
        ]);

        return MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
            'billed' => false,
        ]);
    }

    public function test_create_draft_collects_billable_unexported_time_entries(): void {
        $eligible = $this->makeTimeEntry();
        $this->makeTimeEntry(['billable' => false]);
        $this->makeTimeEntry(['exported' => true]);
        $this->makeTimeEntry(['date' => '2031-01-01']); // außerhalb des Zeitraums

        $transfer = $this->service->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::Datev,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
        );

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertSame(1, $transfer->items()->count());
        $this->assertSame((int) $eligible->id, (int) $transfer->items->first()->source_id);
        $this->assertSame(TimeEntry::class, $transfer->items->first()->source_type);
        $this->assertSame(1, (int) $transfer->position_count);
        $this->assertSame('2.00', (string) $transfer->total_quantity);
        $this->assertNotSame('', (string) $transfer->payload_hash);
        $this->assertSame(64, strlen((string) $transfer->payload_hash));

        // Quellen sind im Draft noch NICHT verbraucht.
        $this->assertFalse((bool) $eligible->fresh()->exported);

        // Hash-Ketten-Event "created" wurde geschrieben.
        $this->assertSame(1, BillingTransferEvent::query()->where('billing_transfer_id', $transfer->id)->where('event', 'created')->count());
    }

    public function test_create_draft_excludes_sources_reserved_in_confirmed_transfer(): void {
        $this->makeTimeEntry();

        $first = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->service->confirm($first);

        $this->expectException(BillingTransferException::class);
        $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
    }

    public function test_create_draft_allows_sources_from_voided_or_draft_transfers(): void {
        $this->makeTimeEntry();

        // Quellen in einem DRAFT-Transfer sind noch nicht reserviert.
        $first = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $second = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Lexoffice);
        $this->assertSame(1, $second->items()->count());

        // Nach void des einen bleibt der andere nutzbar.
        $this->service->void($first);
        $this->service->confirm($second);
        $this->assertSame(TransferStatus::Confirmed, $second->fresh()->status);
    }

    public function test_create_draft_for_material_channel(): void {
        $usage = $this->makeMaterialUsage();
        $this->makeTimeEntry(); // Zeit darf NICHT im Materialkanal landen

        $transfer = $this->service->createDraft(
            $this->customer,
            TransferChannel::Material,
            TransferTarget::Lexoffice,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
        );

        $this->assertSame(1, $transfer->items()->count());
        $item = $transfer->items->first();
        $this->assertSame(MaterialUsage::class, $item->source_type);
        $this->assertSame((int) $usage->id, (int) $item->source_id);
        $this->assertSame('3.00', (string) $item->quantity);
        $this->assertSame('30.00', (string) $item->amount);
    }

    public function test_create_draft_without_sources_throws(): void {
        $this->expectException(BillingTransferException::class);
        $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
    }

    public function test_mark_transferred_consumes_sources_and_writes_chain_event(): void {
        $entry = $this->makeTimeEntry();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File);
        $this->service->confirm($transfer);
        $transfer = $this->service->markTransferred($transfer->fresh(), filePath: 'exports/datev/run-1.zip');

        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertNotNull($transfer->transferred_at);
        $this->assertSame('exports/datev/run-1.zip', $transfer->file_path);
        $this->assertTrue((bool) $entry->fresh()->exported);

        $events = BillingTransferEvent::query()
            ->where('billing_transfer_id', $transfer->id)
            ->orderBy('id')
            ->pluck('event')
            ->all();
        $this->assertSame(['created', 'confirmed', 'transferred'], $events);

        // Hash-Kette ist via audit:verify intakt (gleicher Mechanismus wie
        // bei den übrigen Ketten — config('audit.chains')).
        $this->artisan('audit:verify', ['--chain' => 'billing_transfer_events'])->assertExitCode(0);
    }

    public function test_mark_transferred_consumes_material_sources(): void {
        $usage = $this->makeMaterialUsage();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Material, TransferTarget::Lexoffice);
        $this->service->confirm($transfer);
        $this->service->markTransferred($transfer->fresh());

        $this->assertTrue((bool) $usage->fresh()->billed);
    }

    public function test_transferred_sources_are_excluded_from_local_invoicing(): void {
        $entry = $this->makeTimeEntry();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->service->confirm($transfer);
        $this->service->markTransferred($transfer->fresh());

        // exported=true ⇒ InvoiceGenerator-Filter (billable+!exported) greift.
        $this->assertSame(0, TimeEntry::query()
            ->where('billable', true)->where('exported', false)
            ->whereKey($entry->id)->count());
    }

    public function test_mark_failed_keeps_sources_and_allows_retry(): void {
        $entry = $this->makeTimeEntry();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->service->confirm($transfer);
        $transfer = $this->service->markFailed($transfer->fresh(), 'API nicht erreichbar');

        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertSame('API nicht erreichbar', $transfer->failure_reason);
        $this->assertFalse((bool) $entry->fresh()->exported);

        // Retry: failed → confirmed → transferred.
        $transfer = $this->service->confirm($transfer);
        $this->assertSame(TransferStatus::Confirmed, $transfer->status);
        $transfer = $this->service->markTransferred($transfer);
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertTrue((bool) $entry->fresh()->exported);
    }

    public function test_void_releases_sources_from_confirmed_transfer(): void {
        $entry = $this->makeTimeEntry();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->service->confirm($transfer);
        $transfer = $this->service->void($transfer->fresh());

        $this->assertSame(TransferStatus::Voided, $transfer->status);
        $this->assertFalse((bool) $entry->fresh()->exported);

        // Quelle ist wieder übergabefähig.
        $again = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->assertSame(1, $again->items()->count());
    }

    public function test_void_is_rejected_after_transfer(): void {
        $entry = $this->makeTimeEntry();

        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);
        $this->service->confirm($transfer);
        $transfer = $this->service->markTransferred($transfer->fresh());

        try {
            $this->service->void($transfer);
            $this->fail('void() nach transferred muss fehlschlagen');
        } catch (BillingTransferException $e) {
            $this->assertSame('illegalTransition', $e->reasonCode);
        }

        // Quelle bleibt verbraucht.
        $this->assertTrue((bool) $entry->fresh()->exported);
        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
    }

    public function test_events_are_append_only(): void {
        $this->makeTimeEntry();
        $transfer = $this->service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Datev);

        /** @var BillingTransferEvent $event */
        $event = BillingTransferEvent::query()->where('billing_transfer_id', $transfer->id)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $event->update(['event' => 'manipuliert']);
    }

    public function test_payload_hash_is_deterministic(): void {
        $positions = [
            ['type' => TimeEntry::class, 'id' => 2, 'date' => '2030-04-02', 'quantity' => 1.0, 'amount' => 90.0],
            ['type' => TimeEntry::class, 'id' => 1, 'date' => '2030-04-01', 'quantity' => 2.0, 'amount' => 180.0],
        ];

        $this->assertSame(
            BillingTransferService::hashPositions($positions),
            BillingTransferService::hashPositions(array_reverse($positions)),
        );
    }
}
