<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceBulkApprovalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Exceptions\IncomingInvoiceDecisionException;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sammelfreigabe im Rechnungseingang (MVP-1249): 68 zugeordnete Eingänge
 * standen in „Zu prüfen“, weil die fachliche Freigabe nur je Beleg auf der
 * Detailseite ging. Die Sammelaktion geht denselben Weg wie die
 * Einzelentscheidung und lässt stehen, was am Übergabe-Tor hängt.
 */
class IncomingInvoiceBulkApprovalTest extends TestCase {
    use RefreshDatabase, WithOrganization;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH', 'company' => 'Pinsel GmbH', 'vat_id' => 'DE987654321']);
        $this->actingAs($this->admin);
    }

    public function test_bulk_approval_releases_assigned_open_documents_and_logs_the_decision(): void {
        $received = $this->incoming(['supplier_id' => $this->supplier->id]);
        $question = $this->incoming(['supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Question]);

        $this->post(route('finance.incoming-invoices.bulk-approve'), ['ids' => [$received->sqid, $question->sqid]])
            ->assertRedirect()
            ->assertSessionHas('success', __(':done Eingänge fachlich freigegeben, :skipped übersprungen.', ['done' => 2, 'skipped' => 0]));

        foreach ([$received, $question] as $incoming) {
            $incoming->refresh();
            $this->assertSame(IncomingEInvoiceStatus::Approved, $incoming->status);
            $this->assertSame($this->admin->id, $incoming->decided_by);
            $this->assertNotNull($incoming->decided_at);
            $this->assertTrue($incoming->auditLogs()->where('event', 'incoming_einvoice.decided')->exists());
        }
        $this->assertSame(0, IncomingEInvoice::query()->whereIn('status', [IncomingEInvoiceStatus::Received->value, IncomingEInvoiceStatus::Question->value])->count());
    }

    public function test_bulk_approval_skips_blocked_documents_and_names_the_reasons(): void {
        $unassigned = $this->incoming();
        $mismatch = $this->incoming(['supplier_id' => $this->supplier->id, 'summary' => ['flags' => ['totals_mismatch']]]);
        $approved = $this->incoming(['supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Approved]);
        $clean = $this->incoming(['supplier_id' => $this->supplier->id]);

        $this->post(route('finance.incoming-invoices.bulk-approve'), ['ids' => [$unassigned->sqid, $mismatch->sqid, $approved->sqid, $clean->sqid]])
            ->assertRedirect()
            ->assertSessionHas('warning', static fn (string $message): bool => str_starts_with($message, (string) __(':done Eingänge fachlich freigegeben, :skipped übersprungen.', ['done' => 1, 'skipped' => 3]))
                && str_contains($message, (string) __('Noch keiner Partei zugeordnet.'))
                && str_contains($message, (string) __('Summen widersprüchlich.'))
                && str_contains($message, (string) __('Status :status erlaubt keine fachliche Freigabe.', ['status' => IncomingEInvoiceStatus::Approved->label()])));

        $this->assertSame(IncomingEInvoiceStatus::Approved, $clean->refresh()->status);
        $this->assertSame(IncomingEInvoiceStatus::Received, $unassigned->refresh()->status);
        $this->assertSame(IncomingEInvoiceStatus::Received, $mismatch->refresh()->status);
        $this->assertFalse($mismatch->auditLogs()->where('event', 'incoming_einvoice.decided')->exists());
    }

    public function test_the_button_sits_in_the_review_tab_with_confirmation_but_not_in_the_assign_tab(): void {
        $this->incoming(['supplier_id' => $this->supplier->id]);
        $this->incoming();

        $review = $this->get(route('finance.incoming-invoices.index', ['tab' => 'review']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<button[^>]*formaction="[^"]*sammel-freigeben"[^>]*formnovalidate/', (string) $review);
        $this->assertMatchesRegularExpression('/<button[^>]*formaction="[^"]*sammel-freigeben"[^>]*data-confirm-dialog/', (string) $review);

        $this->get(route('finance.incoming-invoices.index', ['tab' => 'assign']))->assertOk()->assertDontSee('sammel-freigeben');
    }

    public function test_the_single_decision_still_rejects_an_inadmissible_transition(): void {
        $approved = $this->incoming(['supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Approved]);

        $this->post(route('finance.incoming-invoices.decide', $approved), ['decision' => 'question'])
            ->assertRedirect()
            ->assertSessionHas('error', __('Übergang :from → :to ist nicht zulässig.', ['from' => 'approved', 'to' => 'question']));

        $this->expectException(IncomingInvoiceDecisionException::class);
        app(IncomingEInvoiceService::class)->decide($approved->refresh(), IncomingEInvoiceStatus::Question, $this->admin);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes = []): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Structured,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Pinsel GmbH', 'seller_vat_id' => 'DE987654321', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => ['number' => 'RE-1', 'seller' => 'Pinsel GmbH', 'gross' => '119.00', 'recognition' => IncomingInvoiceRecognition::Structured->value],
            ...$attributes,
        ]);
    }

    /** Sicherheitsaudit 2026-10-21, li-7: Zahlungsfreigabe und Ablehnung über zwei veraltete Instanzen — die zweite scheitert am Statusvertrag. */
    public function test_a_stale_second_decision_respects_the_transition_table(): void {
        $incoming = $this->incoming(['supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Approved]);
        $first = IncomingEInvoice::query()->findOrFail($incoming->id);
        $stale = IncomingEInvoice::query()->findOrFail($incoming->id);
        $service = app(IncomingEInvoiceService::class);

        $service->decide($first, IncomingEInvoiceStatus::PaymentReleased, $this->admin);

        $this->expectException(IncomingInvoiceDecisionException::class);
        try {
            $service->decide($stale, IncomingEInvoiceStatus::Rejected, $this->admin);
        } finally {
            $this->assertSame(IncomingEInvoiceStatus::PaymentReleased, $incoming->fresh()->status);
        }
    }
}
