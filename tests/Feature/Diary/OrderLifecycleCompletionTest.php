<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderLifecycleCompletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Enums\Diary\Status;
use App\Enums\Finance\TransferStatus;
use App\Enums\Procedure\ProcedureRunStatus;
use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus};
use App\Events\Invoicing\{InvoiceCancelled, InvoiceIssued};
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Finance\{BillingTransfer, BillingTransferItem};
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureRun;
use App\Models\Project\Project;
use App\Models\Protocol\Protocol;
use App\Models\Time\TimeEntry;
use App\Services\Diary\OrderService;
use App\Services\Finance\BillingTransferService;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-1596: Stoppuhr, Protokolle und Läufe im Lebenszyklus; „Berechnet“ durch den Beleg; Annahme zurückgeben. */
class OrderLifecycleCompletionTest extends TestCase {
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->user()->create();
        $this->project = Project::factory()->create(['organization_id' => $this->user->organization_id]);
    }

    private function order(Status $status): DiaryEntry {
        return DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->user->organization_id,
            'project_id' => $this->project->id,
            'status' => $status,
        ]);
    }

    private function customer(): Customer {
        return Customer::factory()->create(['organization_id' => $this->user->organization_id]);
    }

    private function runningTime(DiaryEntry $order, ?User $user = null): TimeEntry {
        $user ??= $this->user;
        $time = TimeEntry::factory()->create([
            'organization_id' => $order->organization_id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'ended_at' => null,
        ]);
        return $time;
    }

    public function test_pause_stops_the_running_stopwatch_and_books_the_time(): void {
        $order = $this->order(Status::InProgress);
        $time = $this->runningTime($order);

        $paused = app(OrderService::class)->pause($order->refresh(), $this->user, 'material');

        $this->assertSame(Status::WaitingMaterial, $paused->status);
        $time->refresh();
        $this->assertNotNull($time->ended_at);
        $this->assertGreaterThanOrEqual(29, (int) $time->minutes);
        $this->assertSame([$time->id], $paused->journal()->where('event', 'order.pause')->first()->payload['stopped_time_entry_ids']);
    }

    public function test_resume_clears_the_pause_fields(): void {
        $order = $this->order(Status::InProgress);
        $orders = app(OrderService::class);
        $order = $orders->pause($order, $this->user, 'customer', 'Rückruf abwarten');
        $this->assertSame('customer', $order->pause_reason);

        $order = $orders->resume($order, $this->user);

        $this->assertNull($order->paused_at);
        $this->assertNull($order->pause_reason);
        $this->assertNull($order->pause_note);
        $this->assertNotNull($order->resumed_at);
    }

    public function test_complete_is_refused_while_a_stopwatch_runs_and_names_the_person(): void {
        $order = $this->order(Status::InProgress);
        $colleague = User::factory()->user()->create(['organization_id' => $this->user->organization_id, 'name' => 'Kim Beispiel']);
        $this->runningTime($order, $colleague);

        try {
            app(OrderService::class)->complete($order->refresh(), $this->user, 'Fertig.');
            $this->fail('Abschluss trotz laufender Stoppuhr.');
        } catch (InvalidOrderTransitionException $e) {
            $this->assertStringContainsString('Kim Beispiel', $e->getMessage());
        }
        $this->assertSame(Status::InProgress, $order->refresh()->status);
    }

    public function test_cancel_stops_times_archives_open_protocols_and_aborts_running_runs(): void {
        $order = $this->order(Status::InProgress);
        $time = $this->runningTime($order);
        $subject = ['organization_id' => $order->organization_id, 'subject_type' => MorphMap::alias(DiaryEntry::class), 'subject_id' => $order->id];
        $draft = Protocol::factory()->create($subject + ['status' => ProtocolStatus::Draft, 'created_by_user_id' => $this->user->id]);
        $review = Protocol::factory()->create($subject + ['status' => ProtocolStatus::InReview, 'created_by_user_id' => $this->user->id]);
        $signed = Protocol::factory()->create($subject + ['status' => ProtocolStatus::Signed, 'signed_at' => now(), 'created_by_user_id' => $this->user->id]);
        $run = ProcedureRun::factory()->create($subject + ['status' => ProcedureRunStatus::InProgress->value, 'created_by_user_id' => $this->user->id]);
        $doneRun = ProcedureRun::factory()->create($subject + ['status' => ProcedureRunStatus::Completed->value, 'completed_at' => now(), 'created_by_user_id' => $this->user->id]);

        $cancelled = app(OrderService::class)->cancel($order->refresh(), $this->user, 'Kunde hat abgesagt');

        $this->assertSame(Status::Cancelled, $cancelled->status);
        $this->assertNotNull($time->refresh()->ended_at);
        $this->assertSame(ProtocolStatus::Archived, $draft->refresh()->status);
        $this->assertSame(ProtocolStatus::Archived, $review->refresh()->status);
        $this->assertSame(ProtocolStatus::Signed, $signed->refresh()->status);
        $this->assertSame(ProcedureRunStatus::Aborted, $run->refresh()->status);
        $this->assertStringContainsString('Kunde hat abgesagt', (string) $run->abort_reason);
        $this->assertSame(ProcedureRunStatus::Completed, $doneRun->refresh()->status);
    }

    public function test_acceptance_can_be_returned_with_a_reason(): void {
        $order = $this->order(Status::Planned);
        $orders = app(OrderService::class);
        $order = $orders->accept($order, $this->user);

        $this->actingAs($this->user)
            ->post(route('diary.lifecycle', ['diary' => $order, 'action' => 'reject']), ['reason' => 'Falsches Gewerk'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Status::Planned, $order->status);
        $this->assertNull($order->accepted_at);
        $this->assertNull($order->accepted_by_user_id);
        $event = $order->journal()->where('event', 'order.reject')->firstOrFail();
        $this->assertSame('Falsches Gewerk', $event->note);
        $this->assertSame('accepted', $event->from_status);

        $this->actingAs($this->user)
            ->post(route('diary.lifecycle', ['diary' => $order, 'action' => 'reject']), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_handover_stores_the_customer_signer_not_the_employee(): void {
        $order = $this->order(Status::Completed);
        $protocol = Protocol::factory()->create([
            'organization_id' => $order->organization_id,
            'subject_type' => MorphMap::alias(DiaryEntry::class),
            'subject_id' => $order->id,
            'status' => ProtocolStatus::Signed,
            'signed_at' => CarbonImmutable::now(),
            'created_by_user_id' => $this->user->id,
        ]);
        $protocol->signatures()->create([
            'role' => ProtocolSignatureRole::Customer,
            'signer_name' => 'Alex Muster',
            'signed_at' => now(),
            'method' => ProtocolSignatureMethod::cases()[0],
            'hash' => str_repeat('a', 64),
        ]);

        $accepted = app(OrderService::class)->handover($order, $this->user, $protocol, 'wird ignoriert');

        $this->assertSame('Alex Muster', $accepted->accepted_final_by);
        $event = $accepted->journal()->where('event', 'order.handover')->firstOrFail();
        $this->assertSame($this->user->id, $event->actor_user_id);
    }

    public function test_handover_without_customer_signature_takes_the_entered_name(): void {
        $order = $this->order(Status::Completed);
        $protocol = Protocol::factory()->create([
            'organization_id' => $order->organization_id,
            'subject_type' => MorphMap::alias(DiaryEntry::class),
            'subject_id' => $order->id,
            'status' => ProtocolStatus::Signed,
            'signed_at' => CarbonImmutable::now(),
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('diary.lifecycle', ['diary' => $order, 'action' => 'handover']), ['protocol_id' => $protocol->sqid, 'accepted_by' => 'Robin Probe'])
            ->assertSessionHas('success');

        $this->assertSame('Robin Probe', $order->refresh()->accepted_final_by);
    }

    public function test_issued_invoice_marks_the_accepted_order_invoiced_and_its_cancellation_reverts(): void {
        $order = $this->order(Status::AcceptedFinal);
        $time = TimeEntry::factory()->create([
            'organization_id' => $order->organization_id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->user->id,
        ]);
        $order->forceFill(['status' => Status::AcceptedFinal->value])->saveQuietly();
        $invoice = Invoice::factory()->create(['organization_id' => $order->organization_id, 'customer_id' => $this->customer()->id, 'number' => 'R-TEST-0001']);
        InvoiceItem::factory()->create(['organization_id' => $order->organization_id, 'invoice_id' => $invoice->id, 'time_entry_id' => $time->id]);
        $time->forceFill(['exported' => true])->saveQuietly();

        InvoiceIssued::dispatch((int) $invoice->organization_id, (int) $invoice->id);

        $order->refresh();
        $this->assertSame(Status::Invoiced, $order->status);
        $this->assertSame('R-TEST-0001', $order->invoice_reference);
        $event = $order->journal()->where('event', 'order.markInvoiced')->firstOrFail();
        $this->assertNull($event->actor_user_id);
        $this->assertSame('system', $event->actor_kind);
        $this->assertSame($invoice->id, $event->payload['invoice_id']);

        // Solange die Zeit noch an einem Beleg hängt, bleibt „Berechnet“ stehen.
        InvoiceCancelled::dispatch((int) $invoice->organization_id, (int) $invoice->id);
        $this->assertSame(Status::Invoiced, $order->refresh()->status);

        $time->forceFill(['exported' => false])->saveQuietly();
        InvoiceCancelled::dispatch((int) $invoice->organization_id, (int) $invoice->id);
        $order->refresh();
        $this->assertSame(Status::AcceptedFinal, $order->status);
        $this->assertNull($order->invoice_reference);
        $this->assertNull($order->invoiced_at);
    }

    public function test_manual_reference_survives_the_cancellation_of_another_document(): void {
        $order = $this->order(Status::AcceptedFinal);
        $time = TimeEntry::factory()->create([
            'organization_id' => $order->organization_id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->user->id,
        ]);
        $order->forceFill(['status' => Status::AcceptedFinal->value])->saveQuietly();
        app(OrderService::class)->markInvoiced($order->refresh(), $this->user, 'EXT-77');
        $invoice = Invoice::factory()->create(['organization_id' => $order->organization_id, 'customer_id' => $this->customer()->id, 'number' => 'R-TEST-0002']);
        InvoiceItem::factory()->create(['organization_id' => $order->organization_id, 'invoice_id' => $invoice->id, 'time_entry_id' => $time->id]);

        InvoiceCancelled::dispatch((int) $invoice->organization_id, (int) $invoice->id);

        $this->assertSame(Status::Invoiced, $order->refresh()->status);
        $this->assertSame('EXT-77', $order->invoice_reference);
    }

    public function test_completed_transfer_marks_the_order_and_its_cancellation_reverts(): void {
        $order = $this->order(Status::AcceptedFinal);
        $time = TimeEntry::factory()->create([
            'organization_id' => $order->organization_id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->user->id,
        ]);
        $order->forceFill(['status' => Status::AcceptedFinal->value])->saveQuietly();
        $transfer = BillingTransfer::factory()->create([
            'organization_id' => $order->organization_id,
            'customer_id' => $this->customer()->id,
            'status' => TransferStatus::Confirmed,
            'created_by_user_id' => $this->user->id,
        ]);
        BillingTransferItem::factory()->create(['billing_transfer_id' => $transfer->id, 'source_type' => MorphMap::alias(TimeEntry::class), 'source_id' => $time->id]);
        $service = app(BillingTransferService::class);

        $transfer = $service->markTransferred($transfer, null, 'exports/test.csv', $this->user);

        $order->refresh();
        $this->assertSame(Status::Invoiced, $order->status);
        $this->assertSame($transfer->id, $order->journal()->where('event', 'order.markInvoiced')->firstOrFail()->payload['billing_transfer_id']);

        $service->cancel($transfer, 'Beleg verworfen', $this->user);

        $this->assertSame(Status::AcceptedFinal, $order->refresh()->status);
    }

    /** MVP-1624: Eine Materialrechnung berechnet den Auftrag, dessen Zeiten auf dem Stundenzettel des Materials stehen; ihr Storno nimmt es zurück. */
    public function test_material_invoice_marks_the_order_of_its_timesheet_invoiced(): void {
        $order = $this->order(Status::AcceptedFinal);
        $other = $this->order(Status::AcceptedFinal);
        $timesheet = \App\Models\Time\Timesheet::create([
            'organization_id' => $order->organization_id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'work_date' => '2030-04-02',
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
        ]);
        TimeEntry::factory()->create([
            'organization_id' => $order->organization_id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->user->id,
            'timesheet_id' => $timesheet->id,
        ]);
        $order->forceFill(['status' => Status::AcceptedFinal->value])->saveQuietly();
        $material = \App\Models\Material\MaterialUsage::create([
            'organization_id' => $order->organization_id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
            'billed' => true,
        ]);
        $invoice = Invoice::factory()->create(['organization_id' => $order->organization_id, 'customer_id' => $this->customer()->id, 'number' => 'R-MAT-0001', 'category' => Invoice::CATEGORY_MATERIAL]);
        InvoiceItem::factory()->create(['organization_id' => $order->organization_id, 'invoice_id' => $invoice->id, 'material_usage_id' => $material->id]);

        InvoiceIssued::dispatch((int) $invoice->organization_id, (int) $invoice->id);

        $order->refresh();
        $this->assertSame(Status::Invoiced, $order->status);
        $this->assertSame('R-MAT-0001', $order->invoice_reference);
        $this->assertSame(Status::AcceptedFinal, $other->refresh()->status);

        // Solange das Material an einem Beleg hängt, bleibt „Berechnet“ stehen.
        InvoiceCancelled::dispatch((int) $invoice->organization_id, (int) $invoice->id);
        $this->assertSame(Status::Invoiced, $order->refresh()->status);

        $material->forceFill(['billed' => false])->saveQuietly();
        InvoiceCancelled::dispatch((int) $invoice->organization_id, (int) $invoice->id);
        $this->assertSame(Status::AcceptedFinal, $order->refresh()->status);
    }
}
