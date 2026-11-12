<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPayableSettlementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{OpenItemDirection, PostingSourceKind, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Accounting\{AccountingAccount, AccountingOpenItem};
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService, OpenItemService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Billing\Sepa\PaymentProposalService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Skonto, Ausbuchung und „strittig“ an Verbindlichkeiten (Nachzug zu
 * MVP-1555/1559): eigene Gegenkonten statt gewährter Skonti und
 * Forderungsverluste; der strittige Posten sperrt den Zahlungsvorschlag.
 */
class AccountingPayableSettlementTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->org, 'skr03', $startsOn);
        $this->actingAs($this->admin);
        // Der Ausgleich bucht „heute“ — im Testjahr, damit eine Periode besteht.
        $this->travelTo(CarbonImmutable::create(2026, 3, 2, 10));
    }

    /** @return array{0: IncomingEInvoice, 1: AccountingOpenItem} */
    private function payable(): array {
        $invoice = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->org->id])->id,
            'sha256' => hash('sha256', uniqid('payable', true)),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::PaymentReleased,
            'invoice_number' => 'ER-300',
            'seller_name' => 'Lieferant Beispiel',
            'issue_date' => '2026-02-12',
            'due_date' => '2026-03-14',
            'currency' => 'EUR',
            'amount_net' => '1000.00',
            'amount_tax' => '190.00',
            'amount_gross' => '1190.00',
            'creditor_iban' => 'DE89370400440532013000',
            'creditor_bic' => 'COBADEFFXXX',
        ]);
        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::IncomingInvoice)->proposalFor($this->org, $invoice);
        $entry = app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin), $this->admin);

        return [$invoice, AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole()];
    }

    /** @return array<string, array{0: string, 1: string}> Kontonummer ⇒ [Soll, Haben] */
    private function lines(AccountingOpenItem $item, SettlementKind $kind): array {
        $settlement = $item->refresh()->settlements->firstWhere('kind', $kind);
        $this->assertNotNull($settlement?->accounting_entry_id);

        return $settlement->entry->lines->load('account')
            ->mapWithKeys(fn ($line): array => [$line->account->number => [$line->debit->getAmount(), $line->credit->getAmount()]])
            ->all();
    }

    public function test_discount_on_a_payable_books_received_discount(): void {
        [, $item] = $this->payable();
        $this->assertSame(OpenItemDirection::Payable, $item->direction);

        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '23.80', null, 'Skonto 2 %');

        // 2 % Skonto auf 1.190,00 €: 20,00 € netto mindern den Aufwand, 3,80 € die Vorsteuer (§ 17 UStG).
        $this->assertEquals(['3736' => ['0.00', '20.00'], '1576' => ['0.00', '3.80'], '1600' => ['23.80', '0.00']], $this->lines($item, SettlementKind::Discount));
        $this->assertSame('1166.20', $item->refresh()->open_amount?->getAmount());
    }

    public function test_writing_off_a_payable_books_other_income(): void {
        [, $item] = $this->payable();

        app(OpenItemService::class)->settle($item, SettlementKind::WriteOff, '1190.00');

        $lines = $this->lines($item, SettlementKind::WriteOff);
        $this->assertSame(['0.00', '1190.00'], $lines['2709'] ?? null);
        $this->assertArrayNotHasKey('2400', $lines);
    }

    /** Fehlt das Konto der Verbindlichkeitsseite, hilft das der Forderungsseite nicht. */
    public function test_a_payable_needs_its_own_counter_account(): void {
        [, $item] = $this->payable();
        AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '3736')->delete();

        $this->assertSame(['discount' => '3736'], app(OpenItemService::class)->missingCounterAccounts($this->org, OpenItemDirection::Payable));
        $this->assertSame([], app(OpenItemService::class)->missingCounterAccounts($this->org, OpenItemDirection::Receivable));
        $this->get(route('finance.accounting.open-items.settle-form', $item))->assertOk()->assertSee('3736');

        $this->expectException(ValidationException::class);
        app(OpenItemService::class)->settle($item, SettlementKind::Discount, '23.80');
    }

    public function test_the_configured_payable_accounts_win_over_the_defaults(): void {
        [, $item] = $this->payable();
        $settings = (array) $this->org->settings;
        $settings['datev']['payable_write_off_account'] = '2650';
        $this->org->forceFill(['settings' => $settings])->save();

        $this->put(route('finance.datev.config.update'), ['datev' => ['received_discount_account' => '9999']])
            ->assertSessionHasErrors('datev.received_discount_account');
        $this->get(route('finance.datev.config'))->assertOk()
            ->assertSee('name="datev[received_discount_account]"', false)
            ->assertSee('name="datev[payable_write_off_account]"', false);

        app(OpenItemService::class)->settle($item->refresh(), SettlementKind::WriteOff, '90.00');
        $this->assertSame(['0.00', '90.00'], $this->lines($item, SettlementKind::WriteOff)['2650'] ?? null);
    }

    /** Strittige Verbindlichkeit: gesperrt im Zahlungsvorschlag, bis das Kennzeichen zurückgenommen ist. */
    public function test_a_disputed_payable_blocks_the_payment_proposal(): void {
        [$invoice, $item] = $this->payable();
        $proposals = app(PaymentProposalService::class);
        $this->assertNull($proposals->proposalFor($invoice)['blocked']);

        app(OpenItemService::class)->markDisputed($item, 'Lieferung mangelhaft', $this->admin);
        $this->assertSame('disputed', $proposals->proposalFor($invoice)['blocked']);
        $this->get(route('finance.payment-runs.proposals'))->assertOk()->assertSee(__('sepa.blocked.disputed'));

        app(OpenItemService::class)->withdrawDispute($item->refresh(), $this->admin);
        $this->assertNull($proposals->proposalFor($invoice)['blocked']);
    }
}
