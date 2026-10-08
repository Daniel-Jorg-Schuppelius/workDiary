<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterRequirementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\Finance\CostCenter;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, JournalService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** MVP-1000: Kostenstellen-Pflicht am Konto, geprüft beim Festschreiben. */
final class CostCenterRequirementTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private AccountingAccount $expense;

    private AccountingAccount $bank;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-03-10 10:00:00'));
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry, 'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1, 'starts_on' => CarbonImmutable::parse('2026-01-01'), 'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::parse('2026-01-01'));
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        $chart = app(ChartOfAccountsService::class);
        $this->expense = $chart->create($this->org, ['number' => '4930', 'name' => 'Bürobedarf', 'type' => AccountType::Expense]);
        $this->bank = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
    }

    /** Die BWA-Zeile aus dem Kontodialog wird gespeichert — beim Anlegen wie beim Bearbeiten. */
    public function test_account_dialog_saves_the_bwa_group(): void {
        $this->actingAs($this->admin)->put(route('finance.accounting.accounts.update', $this->expense), [
            'number' => '4930', 'name' => 'Bürobedarf', 'type' => 'expense', 'normal_balance' => 'debit', 'bwa_group' => 'material',
        ])->assertSessionHasNoErrors();
        $this->assertSame(\App\Enums\Finance\BwaGroup::Material, $this->expense->refresh()->bwa_group);

        $this->actingAs($this->admin)->post(route('finance.accounting.accounts.store'), [
            'number' => '8400', 'name' => 'Erlöse', 'type' => 'income', 'normal_balance' => 'credit', 'bwa_group' => 'revenue',
        ])->assertSessionHasNoErrors();
        $created = AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '8400')->sole();
        $this->assertSame(\App\Enums\Finance\BwaGroup::Revenue, $created->bwa_group);
    }

    public function test_posting_to_a_required_account_needs_a_cost_center(): void {
        $this->actingAs($this->admin)->put(route('finance.accounting.accounts.update', $this->expense), [
            'number' => '4930', 'name' => 'Bürobedarf', 'type' => 'expense', 'normal_balance' => 'debit', 'is_cost_center_required' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->expense->refresh()->is_cost_center_required);

        $entry = ['booked_on' => '2026-03-10', 'memo' => 'Papier', 'debit_account' => $this->expense->sqid, 'credit_account' => $this->bank->sqid, 'amount' => '40.00', 'post' => '1'];
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $entry)
            ->assertSessionHasErrors(['lines' => __('accounting.ledger.error.cost_center_required', ['account' => $this->expense->displayLabel()])]);
        $this->assertSame(0, AccountingEntry::query()->where('status', AccountingEntryStatus::Posted)->count());

        $office = CostCenter::query()->create(['organization_id' => $this->org->id, 'code' => '100', 'label' => 'Verwaltung', 'active' => true]);
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.create'))->assertOk()->assertSee('100 · Verwaltung');
        $this->actingAs($this->admin)->post(route('finance.accounting.journal.store'), $entry + ['cost_center' => $office->sqid])->assertRedirect();
        $posted = AccountingEntry::query()->where('status', AccountingEntryStatus::Posted)->sole();
        $this->assertSame([$office->id, $office->id], $posted->lines->pluck('cost_center_id')->all());

        $this->expectException(ValidationException::class);
        app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::parse('2026-03-10'), 'memo' => 'Ohne Kostenstelle',
            'lines' => [
                ['accounting_account_id' => $this->expense->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->bank->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);
    }
}
