<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingChartTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTaxCode};
use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService};
use App\Services\Accounting\Posting\PostingSourceRegistry;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontenplan-Vorlagen (Feature 125, MVP-678).
 *
 * Die Vorlagen sind der Einstieg: Konten, Steuerkennzeichen und Regeln in
 * einem Zug — und danach muss ein Beleg ohne Blocker durchlaufen.
 */
class AccountingChartTemplateTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

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
    }

    private function service(): ChartOfAccountsTemplateService {
        return app(ChartOfAccountsTemplateService::class);
    }

    public function test_both_german_templates_are_available(): void {
        $templates = $this->service()->available();

        $this->assertArrayHasKey('skr03', $templates);
        $this->assertArrayHasKey('skr04', $templates);
        // Bewusst nur Deutschland: AT und CH haben ausdrückliche Rechtevorbehalte.
        foreach ($templates as $template) {
            $this->assertSame('DE', $template['country']);
        }
    }

    public function test_applying_skr03_creates_accounts_tax_codes_and_rules(): void {
        $result = $this->service()->apply($this->org, 'skr03', $this->startsOn);

        $this->assertGreaterThan(40, $result['accounts']);
        // Fünf Sätze plus das Kennzeichen für innergemeinschaftliche
        // Lieferungen (MVP-687/688).
        $this->assertSame(6, $result['tax_codes']);
        $this->assertGreaterThan(15, $result['rules']);

        $bank = AccountingAccount::query()->where('number', '1200')->sole();
        $this->assertTrue($bank->is_bank);
        $this->assertSame(AccountType::Asset, $bank->type);

        $receivable = AccountingAccount::query()->where('number', '1400')->sole();
        $this->assertTrue($receivable->is_open_item);

        // Die Vorlage bringt die UStVA-Kennziffern mit (MVP-688).
        $this->assertSame('81', \App\Models\Accounting\AccountingTaxCode::query()->where('code', 'USt19')->sole()->ustva_base_field);
        $this->assertSame('66', \App\Models\Accounting\AccountingTaxCode::query()->where('code', 'VSt19')->sole()->ustva_tax_field);
        $this->assertSame('41', \App\Models\Accounting\AccountingTaxCode::query()->where('code', 'IGL')->sole()->ustva_base_field);

        // Geldtransit- und Sondervorauszahlungskonto gehören dazu.
        $this->assertTrue(AccountingAccount::query()->where('number', '1360')->sole()->is_clearing);
        $this->assertSame(1, AccountingAccount::query()->where('number', '1781')->count());
    }

    public function test_the_tax_code_points_at_its_tax_account(): void {
        $this->service()->apply($this->org, 'skr03', $this->startsOn);

        $code = AccountingTaxCode::query()->where('code', 'USt19')->sole();
        $this->assertSame('1776', $code->taxAccount?->number);
        $this->assertSame('19.00', $code->rate);
    }

    /** Der eigentliche Zweck: nach der Vorlage läuft ein Beleg ohne Blocker durch. */
    public function test_a_sales_invoice_produces_a_postable_proposal_after_applying_the_template(): void {
        $this->service()->apply($this->org, 'skr03', $this->startsOn);

        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-2026-100',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => $this->startsOn->addMonth()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'tax_breakdown' => [['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']],
        ])->refresh();

        $proposal = app(PostingSourceRegistry::class)
            ->for(PostingSourceKind::SalesInvoice)
            ->proposalFor($this->org, $invoice);

        $this->assertSame([], $proposal->blockers);
        $this->assertTrue($proposal->isPostable());
        $this->assertSame('119.00', $proposal->debitTotal());
        $this->assertSame('119.00', $proposal->creditTotal());
    }

    public function test_applying_twice_changes_nothing(): void {
        $first = $this->service()->apply($this->org, 'skr03', $this->startsOn);
        $countAfterFirst = AccountingAccount::query()->count();

        $second = $this->service()->apply($this->org, 'skr03', $this->startsOn);

        $this->assertSame(0, $second['accounts']);
        $this->assertSame(0, $second['tax_codes']);
        $this->assertSame(0, $second['rules']);
        $this->assertSame($countAfterFirst, AccountingAccount::query()->count());
        $this->assertGreaterThan(0, $first['accounts']);
    }

    /** Eigene Konten und Regeln dürfen von der Vorlage nie überschrieben werden. */
    public function test_existing_accounts_and_rules_survive(): void {
        $own = AccountingAccount::query()->create([
            'organization_id' => $this->org->id,
            'number' => '1200',
            'name' => 'Hausbank Sparkasse',
            'type' => AccountType::Asset,
            'normal_balance' => \App\Enums\Finance\BalanceSide::Debit,
            'is_bank' => true,
            'is_active' => true,
        ]);

        $this->service()->apply($this->org, 'skr03', $this->startsOn);

        $this->assertSame('Hausbank Sparkasse', $own->refresh()->name);
    }

    public function test_skr04_uses_its_own_numbers(): void {
        $this->service()->apply($this->org, 'skr04', $this->startsOn);

        $this->assertTrue(AccountingAccount::query()->where('number', '1800')->exists());
        $this->assertTrue(AccountingAccount::query()->where('number', '3806')->exists());
        $this->assertFalse(AccountingAccount::query()->where('number', '8400')->exists());

        $rule = AccountingPostingRule::query()
            ->where('source_kind', PostingSourceKind::SalesInvoice->value)
            ->where('role', PostingAccountRole::Revenue->value)
            ->whereJsonContains('match_criteria->tax_rate', '19.00')
            ->sole();
        $this->assertSame('4400', $rule->account?->number);
    }

    public function test_applying_a_template_over_http_requires_the_configure_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)
            ->post(route('finance.accounting.accounts.template'), ['template' => 'skr03'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('finance.accounting.accounts.template'), ['template' => 'skr03'])
            ->assertRedirect();

        $this->assertGreaterThan(40, AccountingAccount::query()->count());
    }

    public function test_an_unknown_template_is_rejected(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service()->apply($this->org, 'skr99', $this->startsOn);
    }
}
