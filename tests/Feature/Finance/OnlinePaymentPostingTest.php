<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentPostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Enums\Invoicing\{InvoiceStatus, OnlinePaymentStatus};
use App\Models\Accounting\AccountingPostingRule;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService};
use App\Services\Accounting\Posting\Adapters\OnlinePaymentAdapter;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-1067: Online-Zahlung als Buchung — Geldtransit an Forderung, Gebühr und Erstattung. */
final class OnlinePaymentPostingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Invoice $invoice;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $admin);

        $chart = app(ChartOfAccountsService::class);
        foreach ([
            ['1360', 'Geldtransit', AccountType::Asset, PostingAccountRole::PaymentTransit],
            ['1400', 'Forderungen aus Lieferungen und Leistungen', AccountType::Asset, PostingAccountRole::Receivable],
            ['4970', 'Nebenkosten des Geldverkehrs', AccountType::Expense, PostingAccountRole::PaymentFees],
        ] as [$number, $name, $type, $role]) {
            $account = $chart->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type]);
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => PostingSourceKind::OnlinePayment,
                'role' => $role,
                'accounting_account_id' => $account->id,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $startsOn->toDateString(),
                'is_active' => true,
            ]);
        }

        $this->invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'R-2026-0200',
            'status' => InvoiceStatus::Paid,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'total' => '119.00',
            'issued_on' => '2026-06-01',
        ]);
    }

    private function payment(string $refunded = '0.00', ?string $fee = '2.04'): OnlinePayment {
        return OnlinePayment::query()->create([
            'organization_id' => $this->org->id,
            'invoice_id' => $this->invoice->id,
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_' . uniqid(),
            'status' => OnlinePaymentStatus::Paid,
            'currency' => 'EUR',
            'gross_amount' => '119.00',
            'fee_amount' => $fee,
            'refunded_amount' => $refunded,
            'paid_at' => '2026-06-15 10:00:00',
        ]);
    }

    /** @return list<string> */
    private function lines(OnlinePayment $payment): array {
        $proposal = app(OnlinePaymentAdapter::class)->proposalFor($this->org, $payment);
        $this->assertSame([], $proposal->blockers);
        $this->assertSame(['settles_source_type' => $this->invoice->getMorphClass(), 'settles_source_id' => $this->invoice->id, 'settlement_kind' => 'payment'], $proposal->extra);

        return array_map(static fn ($line): string => $line->account->number . ' S ' . $line->debit . ' H ' . $line->credit, $proposal->lines);
    }

    public function test_payment_books_transit_against_receivable_and_fee_against_transit(): void {
        $this->assertSame([
            '1360 S 119.00 H 0.00',
            '1400 S 0.00 H 119.00',
            '4970 S 2.04 H 0.00',
            '1360 S 0.00 H 2.04',
        ], $this->lines($this->payment()));
    }

    public function test_refund_reverses_its_part(): void {
        $this->assertSame([
            '1360 S 119.00 H 0.00',
            '1400 S 0.00 H 119.00',
            '1400 S 19.00 H 0.00',
            '1360 S 0.00 H 19.00',
        ], $this->lines($this->payment('19.00', null)));
    }

    public function test_candidates_are_settled_payments_of_the_period(): void {
        $paid = $this->payment();
        OnlinePayment::query()->create([
            'organization_id' => $this->org->id, 'invoice_id' => $this->invoice->id, 'provider' => 'stripe',
            'status' => OnlinePaymentStatus::Open, 'currency' => 'EUR', 'gross_amount' => '119.00', 'refunded_amount' => '0.00',
        ]);

        $ids = app(OnlinePaymentAdapter::class)
            ->candidates($this->org, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'))
            ->pluck('id')->all();
        $this->assertSame([$paid->id], $ids);
        $this->assertSame([], app(OnlinePaymentAdapter::class)
            ->candidates($this->org, CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-07-31'))->all());
    }

    /**
     * Sicherheitsaudit 2026-10-04, li-8: eine Erstattung nach dem Buchen
     * erschien nie im Buchungseingang — der Satz galt als erledigt.
     */
    public function test_refund_after_posting_brings_the_payment_back_into_the_inbox(): void {
        $inbox = app(\App\Services\Accounting\Posting\PostingInboxService::class);
        $admin = User::query()->where('organization_id', $this->org->id)->firstOrFail();
        $payment = $this->payment('0.00', null);
        $from = CarbonImmutable::parse('2026-06-01');
        $to = CarbonImmutable::parse('2026-06-30');

        $entry = $inbox->prepare($this->org, app(OnlinePaymentAdapter::class)->proposalFor($this->org, $payment), $admin);
        $inbox->post($entry, $admin);
        $this->assertCount(0, $inbox->items($this->org, $from, $to, PostingSourceKind::OnlinePayment));

        $payment->forceFill(['refunded_amount' => '19.00', 'status' => OnlinePaymentStatus::Refunded])->save();

        $items = $inbox->items($this->org, $from, $to, PostingSourceKind::OnlinePayment);
        $this->assertCount(1, $items);
        $this->assertContains((string) __('accounting.inbox.blocker.changed_since_posting'), $items->first()['blockers']);
    }
}
