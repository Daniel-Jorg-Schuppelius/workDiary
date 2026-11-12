<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingManualEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination, RecurringInterval, RecurringRunStatus, RecurringTemplateKind, RecurringTemplateStatus, TaxCodeDirection};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEvent, AccountingOpenItem, AccountingPostingRule, AccountingRecurringRun, AccountingRecurringTemplate, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Finance\CostCenter;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService, RecurringAccountingService};
use App\Services\Accounting\Filing\VatFieldBreakdownService;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Handbuchung mit mehreren Zeilen, Steuerkennzeichen und Gegenpartei;
 * Entwurf bearbeiten und verwerfen; Steuerkennzeichen pflegen
 * (MVP-1556, MVP-1558).
 */
class AccountingManualEntryTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    private AccountingTaxCode $inputTax;

    private AccountingTaxCode $outputTax;

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
            'bank' => ['1200', AccountType::Asset, []],
            'receivable' => ['1400', AccountType::Asset, ['is_open_item' => true]],
            'input_tax' => ['1576', AccountType::Asset, []],
            'output_tax' => ['1776', AccountType::Liability, []],
            'expense' => ['4930', AccountType::Expense, []],
            'revenue' => ['8400', AccountType::Income, []],
        ] as $key => [$number, $type, $flags]) {
            $this->accounts[$key] = $chart->create($this->org, ['number' => $number, 'name' => ucfirst($key), 'type' => $type] + $flags);
        }

        $this->inputTax = $this->taxCode('VSt19', TaxCodeDirection::Input, 'input_tax', ['ustva_tax_field' => '66']);
        $this->outputTax = $this->taxCode('USt19', TaxCodeDirection::Output, 'output_tax', ['ustva_base_field' => '81', 'ustva_tax_field' => '81']);
    }

    /** @param array<string, mixed> $extra */
    private function taxCode(string $code, TaxCodeDirection $direction, string $account, array $extra = []): AccountingTaxCode {
        return AccountingTaxCode::query()->create($extra + [
            'organization_id' => $this->org->id,
            'code' => $code,
            'name' => $code,
            'direction' => $direction,
            'rate' => '19.00',
            'tax_account_id' => $this->accounts[$account]->id,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $rows, array $extra = []): array {
        return $extra + [
            'booked_on' => '2026-02-10',
            'memo' => 'Büromaterial',
            'rows' => [...$rows, ['account' => '', 'debit' => '', 'credit' => '']],
        ];
    }

    private function latestEntry(): AccountingEntry {
        return AccountingEntry::query()->latest('id')->firstOrFail()->load('lines');
    }

    /** Netto in der Zeile, Steuerzeile automatisch; die Kennziffern-Aufteilung erfasst die Handbuchung. */
    public function test_a_line_with_tax_code_creates_the_tax_line_and_feeds_the_vat_fields(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $this->inputTax->sqid, 'memo' => 'Papier'],
            ['account' => '1200', 'credit' => '119.00'],
        ], ['post' => '1']))->assertSessionHasNoErrors()->assertRedirect();

        $entry = $this->latestEntry();
        $this->assertSame(AccountingEntryStatus::Posted, $entry->status);
        $this->assertSame(
            [['4930', '100.00', '0.00'], ['1576', '19.00', '0.00'], ['1200', '0.00', '119.00']],
            $entry->lines->map(fn ($line): array => [$line->account->number, $line->debit->getAmount(), $line->credit->getAmount()])->all(),
        );
        $this->assertSame($this->inputTax->id, $entry->lines[0]->accounting_tax_code_id);
        $this->assertSame('19.00', $entry->lines[0]->tax_amount?->getAmount());
        $this->assertNull($entry->lines[1]->accounting_tax_code_id);

        $fields = collect(app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear())['fields']);
        $this->assertSame('19.00', $fields->firstWhere('field', '66')['tax'] ?? null);
    }

    /** Mehr als zwei Zeilen und Gegenpartei: Der offene Posten trägt den Kunden, die Erlöszeile die Kennziffer. */
    public function test_counterparty_and_three_lines_create_an_open_item_for_the_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '1400', 'debit' => '238.00'],
            ['account' => '8400', 'credit' => '150.00', 'tax_code' => $this->outputTax->sqid],
            ['account' => '8400', 'credit' => '50.00', 'tax_code' => $this->outputTax->sqid],
        ], ['post' => '1', 'counterparty' => 'customer:' . $customer->sqid, 'document_reference' => 'HB-1']))
            ->assertSessionHasNoErrors();

        $entry = $this->latestEntry();
        $this->assertCount(5, $entry->lines);
        $this->assertTrue($entry->isBalanced());

        $item = AccountingOpenItem::query()->where('organization_id', $this->org->id)->sole();
        $this->assertSame(MorphMap::alias(Customer::class), $item->counterparty_type);
        $this->assertSame($customer->id, $item->counterparty_id);
        $this->assertSame('238.00', $item->open_amount?->getAmount());

        $field = collect(app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear())['fields'])->firstWhere('field', '81');
        $this->assertSame(['200.00', '38.00'], [$field['base'] ?? null, $field['tax'] ?? null]);
    }

    public function test_a_row_needs_exactly_one_side_and_a_known_account(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '10.00', 'credit' => '10.00'],
            ['account' => '1200', 'credit' => '10.00'],
        ]))->assertSessionHasErrors('rows.0.account');

        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '9999', 'debit' => '10.00'],
            ['account' => '1200', 'credit' => '10.00'],
        ]))->assertSessionHasErrors('rows.0.account');

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    /** Entwurf bearbeiten: Die erzeugte Steuerzeile erscheint nicht als eigene Zeile und entsteht neu. */
    public function test_a_draft_can_be_edited_and_the_tax_line_is_rebuilt(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $this->inputTax->sqid],
            ['account' => '1200', 'credit' => '119.00'],
        ]))->assertSessionHasNoErrors();
        $entry = $this->latestEntry();
        $this->assertSame(AccountingEntryStatus::Draft, $entry->status);

        $html = (string) $this->actingAs($this->admin)->get(route('finance.accounting.journal.edit', $entry))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="rows\[1\]\[account\]"[^>]*value="1200"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/name="rows\[\d\]\[account\]"[^>]*value="1576"/s', $html);

        $editor = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->actingAs($editor)->put(route('finance.accounting.journal.update', $entry), [
            'booked_on' => '2026-03-05',
            'memo' => 'Büromaterial März',
            'rows' => [
                ['line' => 0, 'account' => '4930', 'debit' => '200.00', 'tax_code' => $this->inputTax->sqid],
                ['line' => 2, 'account' => '1200', 'credit' => '238.00'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('finance.accounting.journal.show', $entry));

        $entry = $entry->refresh()->load('lines');
        $this->assertSame('2026-03-05', $entry->booked_on->toDateString());
        $this->assertSame('Büromaterial März', $entry->memo);
        $this->assertSame(['200.00', '38.00', '0.00'], $entry->lines->map(fn ($line): string => $line->debit->getAmount())->all());
        $this->assertSame($editor->id, $entry->created_by);
        $this->assertSame(1, AccountingEvent::query()->where('event', 'accounting.entry_edited')->count());
    }

    public function test_posted_entries_and_direct_bookings_cannot_be_edited(): void {
        $journal = app(JournalService::class);
        $lines = [
            ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->accounts['revenue']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ];
        $posted = $journal->postDirect($this->org, ['booked_on' => $this->startsOn->addMonth(), 'memo' => 'Fest', 'lines' => $lines], $this->admin);
        $direct = $journal->draft($this->org, [
            'booked_on' => $this->startsOn->addMonth(),
            'memo' => 'Klärung',
            'snapshot' => ['direct_booking' => 'clearing'],
            'lines' => $lines,
        ], $this->admin);

        $this->actingAs($this->admin)->get(route('finance.accounting.journal.edit', $posted))->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.edit', $direct))->assertNotFound();

        $payload = ['booked_on' => '2026-02-10', 'memo' => 'Geändert', 'rows' => [
            ['account' => '1200', 'debit' => '99.00'],
            ['account' => '8400', 'credit' => '99.00'],
        ]];
        $this->actingAs($this->admin)->put(route('finance.accounting.journal.update', $posted), $payload)->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put(route('finance.accounting.journal.update', $direct), $payload)->assertSessionHasErrors('status');

        $this->assertSame('Fest', $posted->refresh()->memo);
        $this->assertSame('10.00', $posted->debitTotal()->getAmount());
        $this->assertSame('Klärung', $direct->refresh()->memo);
    }

    /** Kostenstelle nachtragen — an jedem Entwurf, nie an der Festbuchung (Feature 142). */
    public function test_cost_centers_can_be_assigned_to_draft_lines_only(): void {
        $center = CostCenter::query()->create(['organization_id' => $this->org->id, 'code' => '100', 'label' => 'Verwaltung', 'active' => true]);
        $foreign = CostCenter::query()->withoutGlobalScopes()->create(['organization_id' => Organization::factory()->create()->id, 'code' => '900', 'label' => 'Fremd', 'active' => true]);
        $journal = app(JournalService::class);
        $draft = $journal->draft($this->org, [
            'booked_on' => $this->startsOn->addMonth(),
            'memo' => 'Papier',
            'snapshot' => ['direct_booking' => 'clearing'],
            'lines' => [
                ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);
        $line = $draft->lines->first();

        $this->actingAs($this->admin)->get(route('finance.accounting.journal.cost-centers-form', $draft))->assertOk()->assertSee('100 · Verwaltung');
        $this->actingAs($this->admin)->put(route('finance.accounting.journal.cost-centers', $draft), ['cost_centers' => [$line->sqid => $center->sqid]])
            ->assertSessionHasNoErrors();
        $this->assertSame($center->id, $line->refresh()->cost_center_id);

        $this->actingAs($this->admin)->put(route('finance.accounting.journal.cost-centers', $draft), ['cost_centers' => [$line->sqid => $foreign->sqid]])
            ->assertSessionHasErrors('lines');
        $this->assertSame($center->id, $line->refresh()->cost_center_id);

        $journal->post($draft, $this->admin);
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.cost-centers-form', $draft))->assertNotFound();
        $this->actingAs($this->admin)->put(route('finance.accounting.journal.cost-centers', $draft), ['cost_centers' => [$line->sqid => '']])
            ->assertSessionHasErrors('status');
        $this->assertSame($center->id, $line->refresh()->cost_center_id);
    }

    /** Verwerfen über HTTP: Handbuchung zurück ins Journal; der Lauf der Buchungsvorlage gilt als übersprungen. */
    public function test_manual_and_template_drafts_can_be_discarded(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '10.00'],
            ['account' => '1200', 'credit' => '10.00'],
        ]))->assertSessionHasNoErrors();
        $manual = $this->latestEntry();

        $this->actingAs($this->admin)->get(route('finance.accounting.journal.show', $manual))
            ->assertOk()
            ->assertSee(route('finance.accounting.journal.discard', $manual), false)
            ->assertSee(route('finance.accounting.journal.edit', $manual), false);
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.discard', $manual))
            ->assertRedirect(route('finance.accounting.journal.index'));
        $this->assertNull(AccountingEntry::query()->find($manual->id));

        $template = AccountingRecurringTemplate::query()->create([
            'organization_id' => $this->org->id,
            'kind' => RecurringTemplateKind::PostingTemplate,
            'name' => 'Miete',
            'interval' => RecurringInterval::Monthly,
            'due_day' => 5,
            'starts_on' => $this->startsOn->toDateString(),
            'next_due_on' => '2026-01-05',
            'status' => RecurringTemplateStatus::Active,
            'version' => 1,
            'currency' => CurrencyCode::Euro,
            'template_lines' => [
                ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => '500.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '500.00'],
            ],
        ]);
        $run = app(RecurringAccountingService::class)->runOnce($template, $this->admin);
        $this->assertSame(RecurringRunStatus::DraftCreated, $run?->status);
        $draft = AccountingEntry::query()->findOrFail($run->accounting_entry_id);

        $this->actingAs($this->admin)->post(route('finance.accounting.journal.discard', $draft))
            ->assertRedirect(route('finance.accounting.inbox.index'));

        $run = AccountingRecurringRun::query()->findOrFail($run->id);
        $this->assertSame(RecurringRunStatus::Skipped, $run->status);
        $this->assertNull($run->accounting_entry_id);
        $this->assertSame(2, AccountingEvent::query()->where('event', 'accounting.entry_discarded')->count());
    }

    /** Buchungsvorlage mit Steuerkennzeichen: die Zeilen stehen fertig in der Vorlage, unausgeglichen wird abgewiesen. */
    public function test_a_posting_template_takes_rows_with_tax_code(): void {
        $base = [
            'kind' => RecurringTemplateKind::PostingTemplate->value,
            'name' => 'Leasing',
            'interval' => RecurringInterval::Monthly->value,
            'due_day' => 1,
            'starts_on' => CarbonImmutable::now()->toDateString(),
        ];
        // Die Vorlage bucht ab heute; das Kennzeichen muss am Stichtag gelten.
        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), $base + ['rows' => [
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $this->inputTax->sqid],
            ['account' => '1200', 'credit' => '100.00'],
        ]])->assertSessionHasErrors('rows');
        $this->assertSame(0, AccountingRecurringTemplate::query()->count());

        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), $base + ['rows' => [
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $this->inputTax->sqid],
            ['account' => '1200', 'credit' => '119.00'],
        ]])->assertSessionHasNoErrors();

        $template = AccountingRecurringTemplate::query()->sole();
        $this->assertCount(3, $template->template_lines ?? []);
        $this->assertSame('119.00', $template->expected_amount?->getAmount());

        $html = (string) $this->actingAs($this->admin)->get(route('finance.accounting.recurring.edit', $template))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="rows\[1\]\[account\]"[^>]*value="1200"/s', $html);
    }

    public function test_tax_codes_can_be_created_corrected_and_retired(): void {
        $this->actingAs($this->admin)->get(route('finance.accounting.tax-codes.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('finance.accounting.tax-codes.store'), [
            'code' => 'VSt7',
            'name' => 'Vorsteuer 7 %',
            'direction' => 'input',
            'rate' => '7',
            'tax_account' => $this->accounts['input_tax']->sqid,
            'valid_from' => '2026-01-01',
            'ustva_tax_field' => '66',
        ])->assertSessionHasNoErrors();
        $code = AccountingTaxCode::query()->where('code', 'VSt7')->sole();
        $this->assertSame('7.00', (string) $code->rate);

        // Doppeltes Kürzel und Satz ohne Steuerkonto werden abgewiesen.
        $this->actingAs($this->admin)->post(route('finance.accounting.tax-codes.store'), [
            'code' => 'VSt7', 'name' => 'Doppelt', 'direction' => 'input', 'rate' => '7', 'tax_account' => $this->accounts['input_tax']->sqid, 'valid_from' => '2026-01-01',
        ])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('finance.accounting.tax-codes.store'), [
            'code' => 'VSt5', 'name' => 'Ohne Konto', 'direction' => 'input', 'rate' => '5', 'valid_from' => '2026-01-01',
        ])->assertSessionHasErrors('tax_account');

        // Unbenutzt: direkte Korrektur, keine neue Fassung.
        $this->actingAs($this->admin)->put(route('finance.accounting.tax-codes.update', $code), [
            'name' => 'Vorsteuer ermäßigt', 'direction' => 'input', 'rate' => '5.5', 'tax_account' => $this->accounts['input_tax']->sqid, 'valid_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('5.50', (string) $code->refresh()->rate);
        $this->assertSame(1, AccountingTaxCode::query()->where('code', 'VSt7')->count());

        $this->actingAs($this->admin)->post(route('finance.accounting.tax-codes.deactivate', $code))->assertSessionHasNoErrors();
        $this->assertFalse($code->refresh()->is_active);
        $this->actingAs($this->admin)->get(route('finance.accounting.accounts.index'))->assertOk()->assertSee('VSt7');
    }

    /** Bebucht: Der Satz wechselt nur zum Stichtag; Altbuchung und alte Fassung bleiben, Regeln folgen der neuen. */
    public function test_a_used_tax_code_changes_its_rate_only_from_an_effective_date(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $this->inputTax->sqid],
            ['account' => '1200', 'credit' => '119.00'],
        ], ['post' => '1']))->assertSessionHasNoErrors();
        $rule = AccountingPostingRule::query()->create([
            'organization_id' => $this->org->id,
            'source_kind' => PostingSourceKind::IncomingInvoice,
            'role' => PostingAccountRole::TaxInput,
            'accounting_account_id' => $this->accounts['input_tax']->id,
            'accounting_tax_code_id' => $this->inputTax->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => $this->startsOn->toDateString(),
            'is_active' => true,
        ]);

        $change = ['name' => 'Vorsteuer 20 %', 'direction' => 'input', 'rate' => '20', 'tax_account' => $this->accounts['input_tax']->sqid];
        $this->actingAs($this->admin)->put(route('finance.accounting.tax-codes.update', $this->inputTax), $change + ['valid_from' => '2026-01-01'])
            ->assertSessionHasErrors('valid_from');
        $this->assertSame('19.00', (string) $this->inputTax->refresh()->rate);

        $this->actingAs($this->admin)->put(route('finance.accounting.tax-codes.update', $this->inputTax), $change + ['valid_from' => '2026-07-01'])
            ->assertSessionHasNoErrors();

        $old = $this->inputTax->refresh();
        $next = AccountingTaxCode::query()->where('code', 'VSt19')->where('id', '!=', $old->id)->sole();
        $this->assertSame(['19.00', '2026-06-30'], [(string) $old->rate, $old->valid_to?->toDateString()]);
        $this->assertSame(['20.00', '2026-07-01'], [(string) $next->rate, $next->valid_from->toDateString()]);
        $this->assertSame($next->id, $rule->refresh()->accounting_tax_code_id);
        $this->assertSame($old->id, $this->latestEntry()->lines[0]->accounting_tax_code_id);

        // Eine Buchung nach dem Stichtag rechnet mit der neuen Fassung, auch wenn das Formular die alte nennt.
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $this->payload([
            ['account' => '4930', 'debit' => '100.00', 'tax_code' => $old->sqid],
            ['account' => '1200', 'credit' => '120.00'],
        ], ['booked_on' => '2026-08-01']))->assertSessionHasNoErrors();
        $later = $this->latestEntry();
        $this->assertSame($next->id, $later->lines[0]->accounting_tax_code_id);
        $this->assertSame('20.00', $later->lines[1]->debit->getAmount());
    }

    public function test_the_service_rejects_an_unknown_counterparty(): void {
        $foreign = Customer::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->expectException(ValidationException::class);
        app(\App\Services\Accounting\ManualEntryLines::class)->build($this->org, $this->startsOn, [
            ['account' => '1400', 'debit' => '10.00'],
            ['account' => '8400', 'credit' => '10.00'],
        ], 'customer:' . $foreign->sqid);
    }
}
