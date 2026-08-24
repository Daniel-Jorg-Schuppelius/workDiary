<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\PaymentRunStatus;
use App\Models\{Document, IncomingEInvoice, Organization, User};
use App\Models\Finance\{BankAccount, PaymentRun};
use App\Services\Finance\FinancialFormatsSupport;
use App\Services\Finance\Sepa\{PaymentProposalService, PaymentRunService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SEPA-Zahlungsausgang (Feature 120, MVP-609).
 *
 * Der Kern ist die Reihenfolge: zusammenstellen, freigeben, exportieren. Ein
 * exportierter Lauf ist unveränderlich — sonst wäre die Datei kein Nachweis.
 */
class PaymentRunTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private BankAccount $account;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->account = BankAccount::factory()->create([
            'organization_id' => $this->org->id,
            'label' => 'Geschäftskonto',
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
            'account_holder' => 'Muster GmbH',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(array $attributes = []): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->org->id]);

        return IncomingEInvoice::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoice::STATUS_PAYMENT_RELEASED,
            'invoice_number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'seller_name' => 'Lieferant GmbH',
            'issue_date' => CarbonImmutable::today()->subDays(3)->toDateString(),
            'due_date' => CarbonImmutable::today()->addDays(27)->toDateString(),
            'currency' => 'EUR',
            'amount_gross' => '1190.00',
            'creditor_iban' => 'DE89370400440532013000',
            'creditor_bic' => 'COBADEFFXXX',
        ], $attributes));
    }

    private function service(): PaymentRunService {
        return app(PaymentRunService::class);
    }

    public function test_proposal_lists_released_invoices_only(): void {
        $this->invoice();
        $this->invoice(['status' => IncomingEInvoice::STATUS_APPROVED]);

        $this->assertCount(1, app(PaymentProposalService::class)->proposals());
    }

    public function test_proposal_prefers_the_discount_date_and_reduces_the_amount(): void {
        $invoice = $this->invoice(['discount_percent' => '2.00', 'discount_days' => 10]);

        $proposal = app(PaymentProposalService::class)->proposalFor($invoice);

        $this->assertTrue($proposal['uses_discount']);
        $this->assertSame(1166.20, $proposal['amount']);
        $this->assertSame(
            CarbonImmutable::today()->subDays(3)->addDays(10)->toDateString(),
            $proposal['execute_on']->toDateString(),
        );
    }

    public function test_expired_discount_falls_back_to_the_due_date(): void {
        $invoice = $this->invoice([
            'issue_date' => CarbonImmutable::today()->subDays(30)->toDateString(),
            'discount_percent' => '3.00',
            'discount_days' => 10,
        ]);

        $proposal = app(PaymentProposalService::class)->proposalFor($invoice);

        $this->assertFalse($proposal['uses_discount']);
        $this->assertSame(1190.00, $proposal['amount']);
    }

    public function test_invoice_without_iban_is_blocked(): void {
        $invoice = $this->invoice(['creditor_iban' => null, 'seller_name' => 'Unbekannt GmbH']);

        $this->assertSame('missing_iban', app(PaymentProposalService::class)->proposalFor($invoice)['blocked']);
    }

    /** E3 (Vollscan 2026-08-23): Rechnungs-IBAN ≠ Stammsatz ⇒ Blocker statt stiller Übernahme. */
    public function test_deviating_invoice_iban_blocks_until_confirmed(): void {
        $supplier = \App\Models\Supplier::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Lieferant GmbH',
        ]);
        $supplier->bankAccounts()->create([
            'organization_id' => $this->org->id,
            'account_holder' => 'Lieferant GmbH',
            'iban' => 'DE02120300000000202051',
            'is_primary' => true,
        ]);
        $invoice = $this->invoice(); // creditor_iban DE8937… ≠ Stammsatz

        $proposal = app(PaymentProposalService::class)->proposalFor($invoice);
        $this->assertSame('iban_differs', $proposal['blocked']);

        // Auditierte Bestätigung durch einen Freigabe-Berechtigten hebt den Blocker.
        $this->actingAs($this->admin)
            ->post(route('finance.payment-runs.proposals.confirm-iban', ['invoice' => $invoice]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertNotNull($invoice->creditor_iban_confirmed_at);
        $this->assertNull(app(PaymentProposalService::class)->proposalFor($invoice)['blocked']);
        $this->assertSame(1, $invoice->auditLogs()->where('event', 'incomingEInvoice.ibanConfirmed')->count());
    }

    /** E3: Übereinstimmende oder fehlende Stammsatz-IBAN blockt nicht. */
    public function test_matching_master_iban_does_not_block(): void {
        $supplier = \App\Models\Supplier::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Lieferant GmbH',
        ]);
        $supplier->bankAccounts()->create([
            'organization_id' => $this->org->id,
            'account_holder' => 'Lieferant GmbH',
            'iban' => 'DE89 3704 0044 0532 0130 00', // gleich, nur formatiert
            'is_primary' => true,
        ]);

        $this->assertNull(app(PaymentProposalService::class)->proposalFor($this->invoice())['blocked']);
    }

    public function test_run_collects_positions_and_locks_the_invoices(): void {
        $first = $this->invoice();
        $second = $this->invoice();

        $run = $this->service()->createFromProposals($this->account, $this->admin, [$first->id, $second->id]);

        $this->assertSame(2, $run->items()->count());
        $this->assertSame('2380.00', (string) $run->total);
        // Eine Rechnung im Lauf darf nicht in einen zweiten geraten.
        $this->assertSame($run->id, $first->fresh()?->paid_in_run_id);
        $this->assertCount(0, app(PaymentProposalService::class)->proposals());
    }

    public function test_blocked_position_is_not_taken_into_the_run(): void {
        $ok = $this->invoice();
        $blocked = $this->invoice(['creditor_iban' => null]);

        $run = $this->service()->createFromProposals($this->account, $this->admin, [$ok->id, $blocked->id]);

        $this->assertSame(1, $run->items()->count());
        $this->assertNull($blocked->fresh()?->paid_in_run_id);
    }

    public function test_removing_a_position_makes_the_invoice_payable_again(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $this->service()->removeItem($run->items()->firstOrFail());

        $this->assertNull($invoice->fresh()?->paid_in_run_id);
        $this->assertSame('0.00', (string) $run->fresh()?->total);
    }

    public function test_reduced_amount_requires_a_reason(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);
        $item = $run->items()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->service()->adjustItem($item, 1000.00, null);
    }

    public function test_reduced_amount_with_reason_updates_the_total(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $this->service()->adjustItem($run->items()->firstOrFail(), 1000.00, 'Mängeleinbehalt');

        $this->assertSame('1000.00', (string) $run->fresh()?->total);
        $this->assertSame('Mängeleinbehalt', $run->items()->firstOrFail()->deduction_reason);
    }

    public function test_amount_above_the_invoice_is_rejected(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $this->expectException(RuntimeException::class);
        $this->service()->adjustItem($run->items()->firstOrFail(), 2000.00, 'zu viel');
    }

    public function test_empty_run_cannot_be_released(): void {
        $run = PaymentRun::query()->create([
            'organization_id' => $this->org->id,
            'bank_account_id' => $this->account->id,
            'execution_date' => CarbonImmutable::today(),
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->release($run, $this->admin);
    }

    public function test_release_sets_status_and_actor(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $released = $this->service()->release($run, $this->admin);

        $this->assertSame(PaymentRunStatus::Released, $released->status);
        $this->assertSame($this->admin->id, $released->released_by);
    }

    /**
     * Vollscan 2026-08-23, E4: Der Zahllauf ignorierte das Vier-Augen-Setting
     * des Buchungskerns — Zusammenstellen, Freigeben und Exportieren konnte
     * dieselbe Person. Geldausgang ist der sensiblere Pfad.
     */
    public function test_four_eyes_setting_blocks_release_by_the_creator(): void {
        \App\Support\Setting::set(\App\Services\Accounting\Posting\PostingInboxService::FOUR_EYES_KEY, true, \App\Settings\SettingScope::Organization, $this->org);

        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        try {
            $this->service()->release($run, $this->admin);
            $this->fail('Ersteller durfte den eigenen Zahllauf trotz Vier-Augen-Prinzip freigeben.');
        } catch (RuntimeException $e) {
            $this->assertSame((string) __('sepa.error.four_eyes'), $e->getMessage());
        }

        $other = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $released = $this->service()->release($run->fresh(), $other);

        $this->assertSame(PaymentRunStatus::Released, $released->status);
        $this->assertSame($other->id, $released->released_by);
    }

    public function test_export_without_release_is_rejected(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $this->expectException(RuntimeException::class);
        $this->service()->export($run, $this->admin);
    }

    public function test_export_creates_pain001_and_is_idempotent(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);
        $this->service()->release($run, $this->admin);

        $xml = $this->service()->export($run->refresh(), $this->admin);

        $this->assertStringContainsString('CstmrCdtTrfInitn', $xml);
        $this->assertStringContainsString('DE89370400440532013000', $xml);
        $exported = $run->fresh();
        $this->assertSame(PaymentRunStatus::Exported, $exported?->status);
        $this->assertNotNull($exported?->document_id);

        // Zweiter Abruf: dieselbe Datei, keine neue Message-ID.
        $again = $this->service()->export($exported, $this->admin);
        $this->assertSame($xml, $again);
        $this->assertSame($exported->message_id, $run->fresh()?->message_id);
    }

    public function test_exported_run_cannot_be_cancelled(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht installiert.');
        }
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);
        $this->service()->release($run, $this->admin);
        $this->service()->export($run->refresh(), $this->admin);

        $this->expectException(RuntimeException::class);
        $this->service()->cancel($run->refresh());
    }

    public function test_cancel_frees_the_invoices(): void {
        $invoice = $this->invoice();
        $run = $this->service()->createFromProposals($this->account, $this->admin, [$invoice->id]);

        $this->service()->cancel($run);

        $this->assertNull($invoice->fresh()?->paid_in_run_id);
        $this->assertSame(PaymentRunStatus::Cancelled, $run->fresh()?->status);
    }

    public function test_pages_render_and_run_can_be_created_via_http(): void {
        $invoice = $this->invoice();

        $this->actingAs($this->admin)->get(route('finance.payment-runs.proposals'))->assertOk()->assertSee('Lieferant GmbH');

        $this->actingAs($this->admin)->post(route('finance.payment-runs.store'), [
            'bank_account' => $this->account->sqid,
            'invoices' => [$invoice->sqid],
        ])->assertRedirect();

        $run = PaymentRun::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('finance.payment-runs.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.payment-runs.show', $run))->assertOk()->assertSee('Lieferant GmbH');
    }
}
