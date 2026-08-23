<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\{AccountType, AccountingEntryStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingPostingRule};
use App\Models\{CashEntry, CashRegister, Customer, Expense, IncomingEInvoice, Invoice, Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Buchungs-Inbox und Quellenadapter (Feature 125, MVP-673).
 *
 * Abnahme: Jeder Vorschlag erklärt Betrag, Konten, Steuer, Regelversion und
 * Quelle — und fehlende Mappings blockieren sichtbar, statt auf ein
 * Standardkonto zu raten.
 */
class AccountingInboxTest extends TestCase {
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
        foreach ([
            'receivable' => ['1400', 'Forderungen aus L+L', AccountType::Asset],
            'revenue' => ['8400', 'Erlöse 19 %', AccountType::Income],
            'tax_output' => ['1776', 'Umsatzsteuer 19 %', AccountType::Liability],
            'payable' => ['1600', 'Verbindlichkeiten aus L+L', AccountType::Liability],
            'expense' => ['6300', 'Sonstige Aufwendungen', AccountType::Expense],
            'tax_input' => ['1576', 'Vorsteuer 19 %', AccountType::Asset],
            'cash' => ['1000', 'Kasse', AccountType::Asset],
            'employee' => ['1755', 'Verbindlichkeiten Mitarbeitende', AccountType::Liability],
        ] as $key => [$number, $name, $type]) {
            $this->accounts[$key] = $chart->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type]);
        }
    }

    private function rule(PostingSourceKind $kind, PostingAccountRole $role, string $accountKey, array $match = [], int $priority = 100): AccountingPostingRule {
        return AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => $kind,
            'role' => $role,
            'accounting_account_id' => $this->accounts[$accountKey]->id,
            'match_criteria' => $match === [] ? null : $match,
            'priority' => $priority,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    private function salesRules(): void {
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable');
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']);
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::TaxOutput, 'tax_output', ['tax_rate' => '19.00']);
    }

    private function invoice(): Invoice {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'RE-2026-001',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => $this->startsOn->addMonth()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'tax_breakdown' => [['rate' => '19.00', 'net' => '100.00', 'tax' => '19.00']],
        ]);

        return $invoice->refresh();
    }

    private function inbox(): PostingInboxService {
        return app(PostingInboxService::class);
    }

    private function proposalFor(PostingSourceKind $kind, $source) {
        return app(PostingSourceRegistry::class)->for($kind)->proposalFor($this->org, $source);
    }

    public function test_sales_invoice_proposal_splits_revenue_and_tax(): void {
        $this->salesRules();
        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice());

        $this->assertTrue($proposal->isPostable());
        $this->assertCount(3, $proposal->lines);
        $this->assertSame('119.00', $proposal->debitTotal());
        $this->assertSame('119.00', $proposal->creditTotal());

        $roles = array_map(fn ($line): string => $line->role->value, $proposal->lines);
        $this->assertSame(['receivable', 'revenue', 'tax_output'], $roles);
    }

    /** Die Abnahmebedingung: der Vorschlag erklärt sich selbst. */
    public function test_the_proposal_explains_amount_accounts_tax_rule_and_source(): void {
        $this->salesRules();
        $snapshot = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice())->toSnapshot();

        $this->assertSame('sales_invoice', $snapshot['source_kind']);
        $this->assertStringStartsWith('invoice:', (string) $snapshot['source_key']);
        $this->assertSame('119.00', $snapshot['debit_total']);
        $this->assertNotEmpty($snapshot['rule_version']);
        $this->assertSame('1400 — Forderungen aus L+L', $snapshot['lines'][0]['account']);
        $this->assertStringContainsString('rule:', (string) $snapshot['lines'][0]['rule']);
    }

    public function test_a_missing_rule_blocks_instead_of_guessing_an_account(): void {
        // Nur die Forderung ist gemappt — Erlös und Steuer fehlen.
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable');

        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice());

        $this->assertFalse($proposal->isPostable());
        $this->assertCount(2, $proposal->blockers);
        $this->assertStringContainsString('Erlös', $proposal->blockers[0]);
    }

    public function test_the_more_specific_rule_wins_over_the_fallback(): void {
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable');
        $fallback = $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'cash');
        $specific = $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']);
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::TaxOutput, 'tax_output', ['tax_rate' => '19.00']);

        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice());
        $revenueLine = collect($proposal->lines)->firstWhere('role', PostingAccountRole::Revenue);

        $this->assertNotNull($revenueLine);
        $this->assertSame($this->accounts['revenue']->id, $revenueLine->account->id);
        $this->assertSame($specific->versionTag(), $revenueLine->ruleVersion);
        $this->assertNotSame($fallback->versionTag(), $revenueLine->ruleVersion);
    }

    public function test_preparing_creates_a_ready_entry_with_the_source_snapshot(): void {
        $this->salesRules();
        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice());

        $entry = $this->inbox()->prepare($this->org, $proposal, $this->admin);

        $this->assertSame(AccountingEntryStatus::Ready, $entry->status);
        $this->assertNull($entry->journal_no);
        $this->assertSame($proposal->sourceKey, $entry->source_key);
        $this->assertSame(Invoice::class, $entry->source_type);
        $this->assertNotEmpty($entry->snapshot['lines'] ?? []);
    }

    /** Vorschlag vor Festbuchung: der Adapter bucht nie selbst. */
    public function test_a_blocked_proposal_cannot_be_prepared(): void {
        $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Receivable, 'receivable');
        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice());

        $this->expectException(ValidationException::class);
        $this->inbox()->prepare($this->org, $proposal, $this->admin);
    }

    public function test_the_same_source_is_only_prepared_once(): void {
        $this->salesRules();
        $invoice = $this->invoice();

        $first = $this->inbox()->prepare($this->org, $this->proposalFor(PostingSourceKind::SalesInvoice, $invoice), $this->admin);
        $second = $this->inbox()->prepare($this->org, $this->proposalFor(PostingSourceKind::SalesInvoice, $invoice), $this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AccountingEntry::query()->count());
    }

    public function test_four_eyes_stops_the_preparer_from_posting(): void {
        Setting::set(PostingInboxService::FOUR_EYES_KEY, true, SettingScope::Organization, $this->org);
        $this->salesRules();

        $entry = $this->inbox()->prepare($this->org, $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice()), $this->admin);

        $this->expectException(ValidationException::class);
        $this->inbox()->post($entry, $this->admin);
    }

    public function test_four_eyes_allows_a_second_person_to_post(): void {
        Setting::set(PostingInboxService::FOUR_EYES_KEY, true, SettingScope::Organization, $this->org);
        $this->salesRules();
        $second = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $entry = $this->inbox()->prepare($this->org, $this->proposalFor(PostingSourceKind::SalesInvoice, $this->invoice()), $this->admin);
        $posted = $this->inbox()->post($entry, $second);

        $this->assertSame(AccountingEntryStatus::Posted, $posted->status);
        $this->assertSame($second->id, $posted->posted_by);
    }

    public function test_incoming_invoice_proposal_books_expense_and_input_tax(): void {
        $this->rule(PostingSourceKind::IncomingInvoice, PostingAccountRole::Expense, 'expense');
        $this->rule(PostingSourceKind::IncomingInvoice, PostingAccountRole::TaxInput, 'tax_input');
        $this->rule(PostingSourceKind::IncomingInvoice, PostingAccountRole::Payable, 'payable');

        $document = \App\Models\Document::factory()->create(['organization_id' => $this->org->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', 'incoming-1'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoice::STATUS_APPROVED,
            'invoice_number' => 'ER-77',
            'seller_name' => 'Lieferant GmbH',
            'issue_date' => $this->startsOn->addMonth()->toDateString(),
            'currency' => 'EUR',
            'amount_net' => '100.00',
            'amount_tax' => '19.00',
            'amount_gross' => '119.00',
        ]);

        $proposal = $this->proposalFor(PostingSourceKind::IncomingInvoice, $incoming);

        $this->assertTrue($proposal->isPostable());
        $this->assertSame('119.00', $proposal->debitTotal());
        $this->assertSame('119.00', $proposal->creditTotal());
    }

    public function test_expense_proposal_books_against_the_employee(): void {
        $this->rule(PostingSourceKind::Expense, PostingAccountRole::Expense, 'expense');
        $this->rule(PostingSourceKind::Expense, PostingAccountRole::TaxInput, 'tax_input');
        $this->rule(PostingSourceKind::Expense, PostingAccountRole::EmployeePayable, 'employee');

        $expense = Expense::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'date' => $this->startsOn->addMonth()->toDateString(),
            'description' => 'Bahnfahrt Kundentermin',
            'currency' => 'EUR',
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'tax_amount' => '19.00',
            'amount_gross' => '119.00',
            'status' => ExpenseStatus::Approved->value,
        ]);

        $proposal = $this->proposalFor(PostingSourceKind::Expense, $expense);
        $payableLine = collect($proposal->lines)->firstWhere('role', PostingAccountRole::EmployeePayable);

        $this->assertTrue($proposal->isPostable());
        $this->assertNotNull($payableLine);
        $this->assertSame(User::class, $payableLine->counterpartyType);
        $this->assertSame($this->admin->id, $payableLine->counterpartyId);
    }

    public function test_cash_entry_proposal_follows_the_direction(): void {
        $register = CashRegister::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Ladenkasse',
            'currency' => 'EUR',
            'opened_on' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
        $this->rule(PostingSourceKind::CashEntry, PostingAccountRole::Cash, 'cash');
        $this->rule(PostingSourceKind::CashEntry, PostingAccountRole::Revenue, 'revenue');

        $entry = app(\App\Services\Finance\CashBookService::class)->record($register, [
            'booked_on' => $this->startsOn->addMonth()->toDateString(),
            'direction' => CashEntry::DIRECTION_IN,
            'amount' => '50.00',
            'purpose' => 'Barverkauf',
            'created_by' => $this->admin->id,
        ]);

        $proposal = $this->proposalFor(PostingSourceKind::CashEntry, $entry);
        $cashLine = collect($proposal->lines)->firstWhere('role', PostingAccountRole::Cash);

        $this->assertTrue($proposal->isPostable());
        $this->assertSame('50.00', $cashLine?->debit);
        $this->assertSame('0.00', $cashLine?->credit);
    }

    /**
     * Ein Beleg in fremder Währung wird blockiert, nicht umgerechnet: Ohne
     * belegbaren Kurs (§ 16 Abs. 6 UStG) wäre jede Zahl geraten.
     */
    public function test_a_foreign_currency_document_is_blocked(): void {
        $this->salesRules();
        $invoice = $this->invoice();
        $invoice->update(['currency' => 'CHF']);

        $proposal = $this->proposalFor(PostingSourceKind::SalesInvoice, $invoice->refresh());

        $this->assertFalse($proposal->isPostable());
        $this->assertStringContainsString('CHF', implode(' ', $proposal->blockers));
    }

    public function test_the_inbox_lists_open_and_blocked_items(): void {
        $this->salesRules();
        $this->invoice();

        $items = $this->inbox()->items($this->org, $this->startsOn, $this->startsOn->addYear());

        $this->assertCount(1, $items);
        $this->assertSame('open', $items[0]['state']);
        $this->assertTrue($items[0]['proposal']?->isPostable());
    }

    public function test_the_batch_skips_blocked_items_instead_of_aborting(): void {
        $this->salesRules();
        $this->invoice();

        // Zweiter Beleg ohne Kassenregel → blockiert, darf den Lauf nicht stoppen.
        $register = CashRegister::query()->create([
            'organization_id' => $this->org->id, 'name' => 'Kasse', 'currency' => 'EUR',
            'opened_on' => $this->startsOn->toDateString(), 'is_active' => true,
        ]);
        app(\App\Services\Finance\CashBookService::class)->record($register, [
            'booked_on' => $this->startsOn->addMonth()->toDateString(),
            'direction' => CashEntry::DIRECTION_IN,
            'amount' => '10.00',
            'purpose' => 'Trinkgeld',
            'created_by' => $this->admin->id,
        ]);

        $items = $this->inbox()->items($this->org, $this->startsOn, $this->startsOn->addYear());
        $batch = $items
            ->filter(fn (array $item): bool => $item['state'] === 'open')
            ->map(fn (array $item): array => ['proposal' => $item['proposal']])
            ->values()
            ->all();

        $result = $this->inbox()->processBatch($this->org, $batch, $this->admin, true);

        $this->assertSame(1, $result['prepared']);
        $this->assertSame(1, $result['posted']);
        $this->assertSame(1, AccountingEntry::query()->count());
    }

    public function test_inbox_and_rule_pages_require_permissions(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.inbox.index'))->assertForbidden();
        $this->actingAs($member)->post(route('finance.accounting.inbox.prepare'), [
            'kind' => PostingSourceKind::SalesInvoice->value, 'source_id' => 1,
        ])->assertForbidden();

        $this->actingAs($this->admin)->get(route('finance.accounting.inbox.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.rules.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.rules.create'))->assertOk();
    }

    /** Eine Regeländerung mit neuem Stichtag darf Altbuchungen nicht umdeuten. */
    public function test_editing_a_rule_with_a_new_date_creates_a_follow_up_version(): void {
        $rule = $this->rule(PostingSourceKind::SalesInvoice, PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']);

        $this->actingAs($this->admin)->put(route('finance.accounting.rules.update', $rule), [
            'source_kind' => PostingSourceKind::SalesInvoice->value,
            'role' => PostingAccountRole::Revenue->value,
            'account' => $this->accounts['cash']->sqid,
            'match_key' => 'tax_rate',
            'match_value' => '19.00',
            'priority' => 100,
            'valid_from' => $this->startsOn->addMonths(6)->toDateString(),
        ])->assertRedirect();

        $rules = AccountingPostingRule::query()->orderBy('id')->get();
        $this->assertCount(2, $rules);
        $this->assertSame($this->startsOn->addMonths(6)->subDay()->toDateString(), $rules[0]->valid_to?->toDateString());
        $this->assertSame(2, $rules[1]->version);
    }
}
