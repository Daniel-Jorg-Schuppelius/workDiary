<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExchangeRateConversionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Accounting\{AccountingExchangeRate, AccountingPostingRule};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService};
use App\Services\Accounting\Posting\PostingSourceRegistry;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-1012: Fremdwährungsbelege zum Monatskurs in die Basiswährung. */
final class ExchangeRateConversionTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 3, 10, 9, 0, 0, 'UTC'));
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry, 'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1, 'starts_on' => $startsOn, 'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        $chart = app(ChartOfAccountsService::class);
        $accounts = [
            'receivable' => $chart->create($this->org, ['number' => '1400', 'name' => 'Forderungen', 'type' => AccountType::Asset, 'is_open_item' => true]),
            'revenue' => $chart->create($this->org, ['number' => '8400', 'name' => 'Erlöse 19 %', 'type' => AccountType::Income]),
            'tax' => $chart->create($this->org, ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => AccountType::Liability]),
        ];
        foreach ([[PostingAccountRole::Receivable, 'receivable', []], [PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']], [PostingAccountRole::TaxOutput, 'tax', ['tax_rate' => '19.00']]] as [$role, $key, $match]) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id, 'source_kind' => PostingSourceKind::SalesInvoice, 'role' => $role,
                'accounting_account_id' => $accounts[$key]->id, 'match_criteria' => $match === [] ? null : $match,
                'priority' => 100, 'version' => 1, 'valid_from' => $startsOn->toDateString(), 'is_active' => true,
            ]);
        }
    }

    public function test_foreign_invoices_need_a_monthly_rate_and_are_converted_balanced(): void {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id, 'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'RE-USD-1', 'status' => InvoiceStatus::Issued, 'issued_on' => '2026-03-02', 'due_on' => '2026-03-16', 'currency' => 'USD',
            'subtotal' => '100.00', 'tax_amount' => '19.00', 'total' => '119.00', 'tax_breakdown' => [['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']],
        ])->refresh();
        $adapter = app(PostingSourceRegistry::class)->for(PostingSourceKind::SalesInvoice);

        $blocked = $adapter->proposalFor($this->org, $invoice);
        $this->assertFalse($blocked->isPostable());
        $this->assertContains(__('accounting.inbox.blocker.no_exchange_rate', ['currency' => 'USD', 'month' => '03/2026', 'base' => 'EUR']), $blocked->blockers);

        $this->actingAs($this->admin)->post(route('finance.accounting.exchange-rates.import'), ['import' => "GBP;2026-03;0,8412\nxx"])
            ->assertSessionHasErrors('import');
        $this->actingAs($this->admin)->post(route('finance.accounting.exchange-rates.import'), ['import' => "USD;2026-03;1,0823\nGBP\t03/2026\t0,8412"])
            ->assertRedirect(route('finance.accounting.exchange-rates.index'));
        $this->assertSame(2, AccountingExchangeRate::query()->count());
        $this->actingAs($this->admin)->get(route('finance.accounting.exchange-rates.index'))->assertOk()->assertSee('1,082300')->assertSee('03/2026');

        $proposal = $adapter->proposalFor($this->org, $invoice);
        $this->assertTrue($proposal->isPostable(), implode(' | ', $proposal->blockers));
        $amounts = array_map(static fn ($line): array => [$line->role, $line->debit, $line->credit, $line->taxAmount], $proposal->lines);
        $this->assertContains([PostingAccountRole::Receivable, '109.96', '0.00', null], $amounts);
        $this->assertContains([PostingAccountRole::Revenue, '0.00', '92.40', null], $amounts);
        $this->assertSame($proposal->debitTotal(), $proposal->creditTotal());
        $snapshot = $proposal->toSnapshot();
        $this->assertSame(['currency' => 'USD', 'base' => 'EUR', 'period' => '2026-03', 'rate' => '1.082300', 'source' => 'BMF', 'original_total' => '119.00'], $snapshot['exchange_rate']);
    }
}
