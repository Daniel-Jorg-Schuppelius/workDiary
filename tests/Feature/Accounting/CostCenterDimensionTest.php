<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterDimensionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Accounting;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\{AccountType, PostingAccountRole, PostingSourceKind};
use App\Models\Accounting\{AccountingEntryLine, AccountingPostingRule};
use App\Models\{CostCenterRule, Expense, Organization};
use App\Services\Accounting\JournalService;
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\{AccountLedgerBuilder, TrialBalanceBuilder};
use App\Services\Finance\Datev\DatevBookingFieldResolver;
use App\Services\TimeExport\CostCenterResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Kostenstellen-Dimension (Feature 142, MVP-709): JournalService schreibt
 * `cost_center_id` org-gescopt, Festbuchungen bleiben unveränderlich, die
 * KOST-Regel ist für Journal und DATEV dieselbe.
 */
class CostCenterDimensionTest extends AccountingLedgerTestCase {
    public function test_journal_writes_the_cost_center_per_line(): void {
        $center = $this->costCenter('VERTRIEB');

        $entry = $this->book('revenue', '100.00', CarbonImmutable::create(2026, 2, 3), $center->id);

        $this->assertSame([$center->id, $center->id], $entry->lines->pluck('cost_center_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('VERTRIEB', $entry->lines->first()?->costCenter?->code);
    }

    public function test_foreign_cost_center_is_refused(): void {
        $foreign = $this->costCenter('FREMD', Organization::factory()->create());

        $this->expectException(ValidationException::class);
        $this->book('revenue', '100.00', CarbonImmutable::create(2026, 2, 3), $foreign->id);
    }

    public function test_cost_center_can_be_added_to_a_draft_but_not_to_a_posted_line(): void {
        $center = $this->costCenter('WERK');
        $journal = app(JournalService::class);

        $draft = $journal->draft($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 5),
            'memo' => 'Entwurf',
            'lines' => [
                ['accounting_account_id' => $this->accounts['wages']->id, 'debit' => '10.00', 'credit' => '0.00'],
                ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ], $this->admin);
        $line = $draft->lines->first();
        $this->assertNull($line->cost_center_id);

        $line = $journal->assignCostCenter($line, $center->id);
        $this->assertSame($center->id, (int) $line->cost_center_id);

        $posted = $journal->post($draft, $this->admin);
        $postedLine = $posted->lines->first();

        try {
            $journal->assignCostCenter($postedLine, null);
            $this->fail('Festgeschriebene Zeile wurde geändert.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Auch am Modell vorbei greift der Freeze.
        $this->expectException(RuntimeException::class);
        $postedLine->update(['cost_center_id' => null]);
    }

    public function test_reversal_mirrors_the_cost_center(): void {
        $center = $this->costCenter('BAU');
        $entry = $this->book('rent', '200.00', CarbonImmutable::create(2026, 2, 6), $center->id);

        $reversal = app(JournalService::class)->reverse($entry, 'Falsche Kostenstelle geprüft', $this->admin);

        $this->assertSame([$center->id, $center->id], $reversal->lines->pluck('cost_center_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame((int) $center->id, (int) AccountingEntryLine::query()->find($entry->lines->first()?->id)?->cost_center_id, 'Original bleibt, wie es war');
    }

    /** Eine Regel, zwei Verbraucher: DATEV bekommt den Code, das Journal den Stammsatz. */
    public function test_datev_and_journal_resolve_the_same_cost_center_rule(): void {
        $center = $this->costCenter('KST7');
        CostCenterRule::query()->create(['organization_id' => $this->org->id, 'cost_center_id' => $center->id, 'cost_center' => 'KST7', 'priority' => 0]);
        $personal = $this->costCenter('PERS');
        CostCenterRule::query()->create(['organization_id' => $this->org->id, 'user_id' => $this->admin->id, 'cost_center_id' => $personal->id, 'cost_center' => 'PERS', 'priority' => 10]);

        foreach ([
            'expense' => ['6300', 'Sonstige Aufwendungen', AccountType::Expense],
            'tax_input' => ['1576', 'Vorsteuer 19 %', AccountType::Asset],
            'employee' => ['1755', 'Verbindlichkeiten Mitarbeitende', AccountType::Liability],
        ] as $key => [$number, $name, $type]) {
            $this->accounts[$key] = app(\App\Services\Accounting\ChartOfAccountsService::class)->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type]);
        }
        foreach ([[PostingAccountRole::Expense, 'expense'], [PostingAccountRole::TaxInput, 'tax_input'], [PostingAccountRole::EmployeePayable, 'employee']] as [$role, $key]) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => PostingSourceKind::Expense,
                'role' => $role,
                'accounting_account_id' => $this->accounts[$key]->id,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $this->startsOn->toDateString(),
                'is_active' => true,
            ]);
        }

        $expense = Expense::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'date' => CarbonImmutable::create(2026, 2, 10)->toDateString(),
            'description' => 'Bahnfahrt',
            'currency' => 'EUR',
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'tax_amount' => '19.00',
            'amount_gross' => '119.00',
            'status' => ExpenseStatus::Approved->value,
        ]);

        $resolver = new CostCenterResolver($this->org->id);
        $this->assertSame('PERS', $resolver->codeForSource($expense), 'Auslage → Benutzerregel');
        $this->assertSame($personal->id, $resolver->idForSource($expense));
        $this->assertSame('KST7', $resolver->codeForSource(null), 'ohne Personenbezug → Org-Default');
        $this->assertSame('PERS', (new DatevBookingFieldResolver($this->org->id))->forSource($expense)['cost_center1']);

        $proposal = app(PostingSourceRegistry::class)->for(PostingSourceKind::Expense)->proposalFor($this->org, $expense);
        $entry = app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin);

        $this->assertTrue($entry->lines->isNotEmpty());
        $this->assertSame([$personal->id], $entry->lines->pluck('cost_center_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
    }

    public function test_trial_balance_and_account_ledger_filter_by_cost_center(): void {
        $center = $this->costCenter('NORD');
        $this->book('revenue', '100.00', CarbonImmutable::create(2026, 3, 1), $center->id);
        $this->book('revenue', '40.00', CarbonImmutable::create(2026, 3, 2));
        $from = CarbonImmutable::create(2026, 3, 1);
        $to = CarbonImmutable::create(2026, 3, 31);

        $all = app(TrialBalanceBuilder::class)->build($this->org, $from, $to);
        $filtered = app(TrialBalanceBuilder::class)->build($this->org, $from, $to, $center->id);
        $this->assertSame('140.00', $all['totals']['credit']);
        $this->assertSame('100.00', $filtered['totals']['credit']);

        $ledger = app(AccountLedgerBuilder::class)->build($this->org, $this->accounts['revenue'], $from, $to, null, $center->id);
        $this->assertCount(1, $ledger['lines']);
        $this->assertSame('-100.00', $ledger['closing']);
    }
}
