<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingRetentionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Models\Document\Document;
use App\Models\Finance\{BankAccount, IncomingInvoiceRetention, PaymentRun, PaymentRunItem};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Sepa\{IncomingRetentionService, PaymentProposalService, PaymentRunService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** MVP-953: Einbehalte an Eingangsrechnungen im Zahllauf. */
final class IncomingRetentionTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private BankAccount $account;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->account = BankAccount::factory()->create(['organization_id' => $this->org->id, 'label' => 'Geschäftskonto', 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'account_holder' => 'Muster GmbH']);
    }

    private function invoice(): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->org->id, 'document_type' => \App\Enums\Document\DocumentType::Invoice]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'upload', 'received_at' => now(), 'status' => IncomingEInvoice::STATUS_PAYMENT_RELEASED,
            'invoice_number' => 'RE-4711', 'seller_name' => 'Rohbau GmbH',
            'issue_date' => CarbonImmutable::today()->subDays(3)->toDateString(), 'due_date' => CarbonImmutable::today()->addDays(27)->toDateString(),
            'currency' => 'EUR', 'amount_gross' => '10000.00', 'creditor_iban' => 'DE89370400440532013000', 'creditor_bic' => 'COBADEFFXXX',
        ]);
    }

    public function test_retention_reduces_payment_and_is_paid_separately_after_release(): void {
        $invoice = $this->invoice();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.retentions.store', $invoice), ['kind' => RetentionKind::Warranty->value, 'percent' => '5', 'due_on' => '2031-06-30'])->assertSessionHas('success');
        $retention = IncomingInvoiceRetention::query()->sole();
        $this->assertSame('500.00', (string) $retention->amount);

        $proposal = app(PaymentProposalService::class)->proposalFor($invoice->fresh());
        $this->assertSame(9500.0, $proposal['amount']);
        $this->assertSame(500.0, $proposal['retained']);

        $run = app(PaymentRunService::class)->createFromProposals($this->account, $this->admin, [$invoice->id]);
        $item = $run->items()->sole();
        $this->assertSame('9500.00', (string) $item->amount);
        $this->assertSame(__('sepa.retention.deduction'), $item->deduction_reason);

        // Eine Rechnung im Zahllauf nimmt keinen neuen Einbehalt mehr an.
        $this->expectExceptionMessage(__('sepa.retention.error.in_run'));
        try {
            app(IncomingRetentionService::class)->add($invoice->fresh(), RetentionKind::Performance, null, '100', null, null, $this->admin);
        } finally {
            $this->actingAs($this->admin)->post(route('finance.incoming-invoices.retentions.release', $retention))->assertSessionHas('success');
            $this->assertSame(RetentionStatus::Released, $retention->fresh()->status);
            $this->assertSame(1, app(IncomingRetentionService::class)->payable()->count());

            $this->actingAs($this->admin)->get(route('finance.payment-runs.proposals'))->assertOk()->assertSeeText(__('sepa.retention.payable_title'));
            $this->actingAs($this->admin)->post(route('finance.payment-runs.store'), ['bank_account' => $this->account->sqid, 'retentions' => [$retention->sqid]])->assertRedirect();

            $retentionItem = PaymentRunItem::query()->whereNotNull('incoming_invoice_retention_id')->sole();
            $this->assertSame('500.00', (string) $retentionItem->amount);
            $this->assertNull($retentionItem->incoming_einvoice_id);
            $this->assertNotNull($retention->fresh()->paid_in_run_id);

            // Entfernen gibt nur den Einbehalt frei, nicht die Rechnung.
            app(PaymentRunService::class)->removeItem($retentionItem);
            $this->assertNull($retention->fresh()->paid_in_run_id);
            $this->assertSame($run->id, $invoice->fresh()->paid_in_run_id);
        }
    }

    public function test_retentions_cannot_exceed_the_invoice_and_open_ones_can_be_removed(): void {
        $invoice = $this->invoice();
        $service = app(IncomingRetentionService::class);
        $retention = $service->add($invoice, RetentionKind::Performance, null, '9000', null, null, $this->admin);

        try {
            $service->add($invoice, RetentionKind::Warranty, null, '1500', null, null, $this->admin);
            $this->fail('Einbehalte über dem Rechnungsbetrag wurden angenommen.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('sepa.retention.error.exceeds'), $e->getMessage());
        }

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $invoice->document))->assertOk()->assertSeeText(__('sepa.retention.title'));
        $this->actingAs($this->admin)->delete(route('finance.incoming-invoices.retentions.destroy', $retention))->assertSessionHas('success');
        $this->assertSame(0, IncomingInvoiceRetention::query()->count());
        $this->assertSame(0, PaymentRun::query()->count());
    }

    public function test_overdue_retention_is_reported(): void {
        User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $invoice = $this->invoice();
        app(IncomingRetentionService::class)->add($invoice, RetentionKind::Warranty, null, '300', CarbonImmutable::yesterday(), null, $this->admin);

        $sent = app(\App\Services\Finance\DeadlineScans\IncomingRetentionReleaseScan::class)->run(app(\App\Services\Notification\NotificationDispatcher::class), new \App\Services\Notification\DeadlineScans\DeadlineScanOptions(7, 30));

        $this->assertGreaterThan(0, $sent);
    }
}
