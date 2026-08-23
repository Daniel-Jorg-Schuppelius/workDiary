<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingTaxationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, ProfitDetermination, SettlementKind, TaxationMethod};
use App\Models\Accounting\{AccountingAccount, AccountingEvent, AccountingPostingRule, AccountingTaxationPeriod};
use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Accounting\{AccountingProfileService, AccountingReportService, ChartOfAccountsService, FiscalYearService, JournalService, TaxationMethodResolver};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Versteuerungsart Soll/Ist (Feature 125, MVP-679).
 *
 * Abnahme: Dieselbe Rechnung erscheint bei Soll im Monat des Belegs, bei Ist
 * im Monat der Zahlung — aus einem Datenbestand, ohne zweiten Weg.
 */
class AccountingTaxationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        $this->accounts['receivable'] = $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]);
        $this->accounts['revenue'] = $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]);
        $this->accounts['vat'] = $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);

        // Das Steuerkonto muss als solches benannt sein, sonst taucht es in der
        // USt-Auswertung nicht auf.
        AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => \App\Enums\Finance\PostingSourceKind::SalesInvoice,
            'role' => \App\Enums\Finance\PostingAccountRole::TaxOutput,
            'accounting_account_id' => $this->accounts['vat']->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    private function resolver(): TaxationMethodResolver {
        return app(TaxationMethodResolver::class);
    }

    /**
     * Rechnung im Januar: 100 netto + 19 USt. Die Buchung trägt ihre Quelle —
     * ohne sie findet der Ausgleich später seinen offenen Posten nicht.
     */
    private function invoiceEntry(): Invoice {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-1',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => $this->startsOn->addDays(10)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(10),
            'document_on' => $this->startsOn->addDays(10),
            'memo' => 'Rechnung Januar',
            'document_reference' => 'RE-1',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'source_key' => 'tax-test:invoice',
            'lines' => [
                ['accounting_account_id' => $this->accounts['receivable']->id, 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $this->accounts['vat']->id, 'debit' => '0.00', 'credit' => '19.00'],
            ],
        ], $this->admin);

        return $invoice;
    }

    /**
     * Zahlung im März gegen die Forderung. Ein Rückläufer dreht die Seiten —
     * er erhöht den Posten wieder, statt ihn ein zweites Mal zu mindern.
     */
    private function paymentEntry(Invoice $invoice, string $amount, SettlementKind $kind = SettlementKind::Payment): void {
        $refund = $kind === SettlementKind::Reversal;
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addMonths(2),
            'memo' => 'Zahlung',
            'source_key' => 'tax-test:payment:' . uniqid('', true),
            'snapshot' => [
                'settles_source_type' => Invoice::class,
                'settles_source_id' => $invoice->id,
                'settlement_kind' => $kind->value,
            ],
            'lines' => [
                [
                    'accounting_account_id' => $this->accounts['bank']->id,
                    'debit' => $refund ? '0.00' : $amount,
                    'credit' => $refund ? $amount : '0.00',
                ],
                [
                    'accounting_account_id' => $this->accounts['receivable']->id,
                    'debit' => $refund ? $amount : '0.00',
                    'credit' => $refund ? '0.00' : $amount,
                ],
            ],
        ], $this->admin);
    }

    private function vat(CarbonImmutable $from, CarbonImmutable $to): array {
        return app(AccountingReportService::class)->vatPreview($this->org, $from, $to);
    }

    public function test_the_default_is_accrual_taxation(): void {
        $this->assertSame(TaxationMethod::Debit, $this->resolver()->at($this->org));
        $this->assertSame(0, AccountingTaxationPeriod::query()->count());
    }

    /** Soll: Die Steuer steht im Monat des Belegs. */
    public function test_accrual_reports_the_tax_in_the_invoice_month(): void {
        $this->invoiceEntry();

        $january = $this->vat($this->startsOn, $this->startsOn->endOfMonth());
        $march = $this->vat($this->startsOn->addMonths(2), $this->startsOn->addMonths(2)->endOfMonth());

        $this->assertSame('19.00', $january['output']);
        $this->assertSame('0.00', $march['output']);
        $this->assertSame(TaxationMethod::Debit, $january['method']);
    }

    /** Ist: Dieselbe Rechnung erscheint erst im Monat der Zahlung. */
    public function test_cash_basis_reports_the_tax_in_the_payment_month(): void {
        $invoice = $this->invoiceEntry();
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn, $this->admin, 'Genehmigung des Finanzamts');
        $this->paymentEntry($invoice, '119.00');

        $january = $this->vat($this->startsOn, $this->startsOn->endOfMonth());
        $march = $this->vat($this->startsOn->addMonths(2), $this->startsOn->addMonths(2)->endOfMonth());

        $this->assertSame('0.00', $january['output']);
        $this->assertSame('19.00', $march['output']);
        $this->assertSame(TaxationMethod::Credit, $march['method']);
    }

    /** Eine Teilzahlung trägt nur ihren Anteil der Steuer. */
    public function test_a_partial_payment_carries_a_proportional_share_of_the_tax(): void {
        $invoice = $this->invoiceEntry();
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn, $this->admin, null);
        $this->paymentEntry($invoice, '59.50');

        $march = $this->vat($this->startsOn->addMonths(2), $this->startsOn->addMonths(2)->endOfMonth());

        // Die Hälfte des Belegs → die Hälfte der Steuer.
        $this->assertSame('9.50', $march['output']);
    }

    /** Ein Rückläufer nimmt die vereinnahmte Steuer wieder heraus. */
    public function test_a_reversal_removes_the_collected_tax_again(): void {
        $invoice = $this->invoiceEntry();
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn, $this->admin, null);
        $this->paymentEntry($invoice, '119.00');
        $this->paymentEntry($invoice, '119.00', SettlementKind::Reversal);

        $march = $this->vat($this->startsOn->addMonths(2), $this->startsOn->addMonths(2)->endOfMonth());

        $this->assertSame('0.00', $march['output']);
    }

    /** Die Vorsteuer bleibt in beiden Methoden gleich. */
    public function test_input_tax_is_unaffected_by_the_method(): void {
        $inputTax = app(ChartOfAccountsService::class)->create($this->org, [
            'number' => '1576', 'name' => 'Vorsteuer 19 %', 'type' => AccountType::Asset,
        ]);
        AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => \App\Enums\Finance\PostingSourceKind::IncomingInvoice,
            'role' => \App\Enums\Finance\PostingAccountRole::TaxInput,
            'accounting_account_id' => $inputTax->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);

        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => $this->startsOn->addDays(12),
            'memo' => 'Eingangsrechnung',
            'source_key' => 'tax-test:incoming',
            'lines' => [
                ['accounting_account_id' => $inputTax->id, 'debit' => '19.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '19.00'],
            ],
        ], $this->admin);

        $accrual = $this->vat($this->startsOn, $this->startsOn->endOfMonth());
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn, $this->admin, null);
        $cash = $this->vat($this->startsOn, $this->startsOn->endOfMonth());

        $this->assertSame('19.00', $accrual['input']);
        $this->assertSame($accrual['input'], $cash['input']);
    }

    /** Der Wechsel hält fest, welche offenen Posten betroffen sind — und blockiert nicht. */
    public function test_the_switch_records_the_open_items_without_blocking(): void {
        $this->invoiceEntry();

        $period = $this->resolver()->switchTo(
            $this->org,
            TaxationMethod::Credit,
            $this->startsOn->addMonth(),
            $this->admin,
            'Antrag genehmigt',
        );

        $this->assertSame(1, $period->changeover['count']);
        $this->assertSame('119.00', $period->changeover['open_amount']);
        $this->assertContains('accounting.taxation_method_switched', AccountingEvent::query()->pluck('event')->all());
    }

    public function test_the_method_is_resolved_per_date(): void {
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn->addMonths(6), $this->admin, null);

        $this->assertSame(TaxationMethod::Debit, $this->resolver()->at($this->org, $this->startsOn->addMonth()));
        $this->assertSame(TaxationMethod::Credit, $this->resolver()->at($this->org, $this->startsOn->addMonths(7)));
    }

    public function test_switching_to_the_same_method_is_refused(): void {
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn, $this->admin, null);

        $this->expectException(ValidationException::class);
        $this->resolver()->switchTo($this->org, TaxationMethod::Credit, $this->startsOn->addMonth(), $this->admin, null);
    }

    public function test_the_taxation_dialog_and_switch_are_permission_gated(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.taxation.create'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('finance.accounting.taxation.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('finance.accounting.taxation'), [
            'method' => TaxationMethod::Credit->value,
            'valid_from' => $this->startsOn->addYear()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, AccountingTaxationPeriod::query()->count());
    }
}
