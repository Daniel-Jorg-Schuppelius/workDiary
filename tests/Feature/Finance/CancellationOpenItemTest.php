<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CancellationOpenItemTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AllocationKind, BillingMode, OpenItemStatus, PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Models\Accounting\{AccountingEntry, AccountingOpenItem, AccountingOpenItemSettlement};
use App\Models\Customer\Customer;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService, OpenItemService};
use App\Services\Accounting\Posting\Adapters\{PaymentAdapter, SalesInvoiceAdapter};
use App\Services\Accounting\Posting\{PostingInboxService, PostingProposal};
use App\Services\Accounting\Reports\EuerPreviewBuilder;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1545: Der Stornobeleg gleicht in der lokalen Buchhaltung genau den
 * offenen Posten der stornierten Rechnung aus — vorher traf er den ältesten
 * offenen Posten des Kunden. Er bucht nach seiner Rechnung und zählt nie als
 * Zahlung.
 */
final class CancellationOpenItemTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-03-01 09:00:00');
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel Haustechnik GmbH', 'billing_mode' => BillingMode::Workdiary]);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->organization, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->organization, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->organization, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->organization, 'skr03', $startsOn);
    }

    /** Ausgestellte Rechnung über einen Nettobetrag zu 19 %. */
    private function issued(string $net, string $day): Invoice {
        $this->travelTo($day . ' 10:00:00');
        $invoice = app(InvoiceGenerator::class)->emptyDraft($this->customer);
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Wartung Heizanlage', 'quantity' => '1', 'unit' => 'Psch.', 'unit_price' => $net, 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
    }

    private function cancel(Invoice $invoice, string $day): Invoice {
        $this->travelTo($day . ' 10:00:00');
        $cancellation = app(InvoiceGenerator::class)->cancellationFor($invoice, 'Doppelt abgerechnet', $this->admin->id);

        return app(InvoiceIssueService::class)->issue($cancellation)->refresh();
    }

    private function proposal(Invoice $invoice): PostingProposal {
        return app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice);
    }

    private function postProposal(PostingProposal $proposal): AccountingEntry {
        $this->assertSame([], $proposal->blockers);

        return app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->organization, $proposal, $this->admin), $this->admin);
    }

    private function item(Invoice $invoice): AccountingOpenItem {
        return AccountingOpenItem::query()->where('source_type', MorphMap::alias(Invoice::class))->where('source_id', $invoice->id)->sole();
    }

    private function payByBank(Invoice $invoice, string $amount, string $day): AccountingEntry {
        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'booking_date' => $day,
            'amount' => $amount,
        ]);
        $allocation = PaymentAllocation::query()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $transaction->id,
            'allocatable_type' => $invoice->getMorphClass(),
            'allocatable_id' => $invoice->id,
            'amount' => $amount,
            'kind' => AllocationKind::Payment,
            'confirmed_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        return $this->postProposal(app(PaymentAdapter::class)->proposalFor($this->organization, $allocation));
    }

    /** @return list<string> */
    private function lines(PostingProposal $proposal): array {
        return array_map(static fn ($line): string => $line->account->number . ' S ' . $line->debit . ' H ' . $line->credit, $proposal->lines);
    }

    public function test_cancellation_settles_the_open_item_of_its_invoice_not_the_oldest_one(): void {
        $older = $this->issued('100.00', '2026-03-02');
        $this->postProposal($this->proposal($older));
        $younger = $this->issued('200.00', '2026-03-10');
        $this->postProposal($this->proposal($younger));
        $payment = $this->payByBank($older, '50.00', '2026-03-12');
        // Die Bankzahlung nennt den Kunden mit dem Morph-Alias, nie mit dem Klassennamen.
        $receivable = $payment->lines()->whereNotNull('counterparty_type')->sole();
        $this->assertSame(MorphMap::alias(Customer::class), $receivable->counterparty_type);
        $this->assertTrue($this->customer->is($receivable->counterparty));

        $proposal = $this->proposal($this->cancel($younger, '2026-03-15'));

        $this->assertSame(['1400 S 0.00 H 238.00', '8400 S 200.00 H 0.00', '1776 S 38.00 H 0.00'], $this->lines($proposal));
        $this->assertSame([MorphMap::alias(Invoice::class), $younger->id, SettlementKind::Cancellation->value], [
            $proposal->extra['settles_source_type'] ?? null, $proposal->extra['settles_source_id'] ?? null, $proposal->extra['settlement_kind'] ?? null,
        ]);
        $this->postProposal($proposal);

        $item = $this->item($younger);
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame('0.00', $item->open_amount?->getAmount());
        $settlement = AccountingOpenItemSettlement::query()->where('accounting_open_item_id', $item->id)->sole();
        $this->assertSame(SettlementKind::Cancellation, $settlement->kind);
        $this->assertSame('238.00', $settlement->amount?->getAmount());

        $olderItem = $this->item($older);
        $this->assertSame(OpenItemStatus::PartiallySettled, $olderItem->status, 'Die ältere Rechnung trägt nur ihre eigene Zahlung.');
        $this->assertSame('69.00', $olderItem->open_amount?->getAmount());

        // Eingenommen ist nur die Bankzahlung: Der Stornobeleg ist kein Zahlungseingang.
        $euer = app(EuerPreviewBuilder::class)->build($this->organization, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));
        $this->assertSame('50.00', $euer['income']);
    }

    public function test_cancellation_waits_in_the_inbox_until_its_invoice_is_posted(): void {
        $invoice = $this->issued('100.00', '2026-03-02');
        $cancellation = $this->cancel($invoice, '2026-03-05');
        $invoice->refresh();
        $inbox = app(PostingInboxService::class);
        $from = CarbonImmutable::parse('2026-03-01');
        $to = CarbonImmutable::parse('2026-03-31');

        // Die stornierte Rechnung bleibt zu buchen; ihr Stornobeleg wartet auf sie.
        $items = $inbox->items($this->organization, $from, $to, PostingSourceKind::SalesInvoice);
        $this->assertEqualsCanonicalizing([$invoice->id, $cancellation->id], $items->map(static fn (array $item): int => (int) $item['source']->getKey())->all());
        $waiting = $items->first(static fn (array $item): bool => (int) $item['source']->getKey() === $cancellation->id);
        $this->assertContains((string) __('accounting.inbox.blocker.cancelled_invoice_unposted', ['number' => (string) $invoice->number]), $waiting['blockers'] ?? []);

        $this->postProposal($this->proposal($invoice));
        $this->postProposal($this->proposal($cancellation));

        $this->assertSame(OpenItemStatus::Settled, $this->item($invoice)->status);
        $this->assertCount(0, $inbox->items($this->organization, $from, $to, PostingSourceKind::SalesInvoice), 'Beide Belege sind gebucht, keiner gilt als geändert.');
    }

    public function test_cancellation_waits_while_another_settlement_reduced_the_item(): void {
        $invoice = $this->issued('100.00', '2026-03-02');
        $this->postProposal($this->proposal($invoice));
        $item = app(OpenItemService::class)->settle($this->item($invoice), SettlementKind::Retention, '19.00', null, 'Einbehalt Mängel')?->openItem;
        $this->assertInstanceOf(AccountingOpenItem::class, $item);
        $this->assertTrue($invoice->refresh()->canBeCancelled(), 'Ausgestellt bleibt der Storno möglich.');

        $proposal = $this->proposal($this->cancel($invoice, '2026-03-05'));

        $this->assertContains((string) __('accounting.inbox.blocker.cancelled_invoice_settled', [
            'number' => (string) $invoice->number,
            'open' => $item->open_amount?->format() ?? '',
        ]), $proposal->blockers);
    }
}
