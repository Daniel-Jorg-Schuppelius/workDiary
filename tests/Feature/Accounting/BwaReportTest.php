<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BwaReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Accounting;

use App\Enums\Finance\{AccountType, BwaGroup};
use App\Models\Accounting\AccountingAccount;
use App\Models\User;
use App\Services\Accounting\AccountingBudgetService;
use App\Services\Accounting\Reports\{BwaAccountMapper, BwaBuilder, ProfitAndLossBuilder};
use Carbon\CarbonImmutable;

/**
 * BWA (Feature 142, MVP-709): Gruppensummen, Zwischensummen, Vergleichsmodi,
 * Budget-Abweichung, nicht zugeordnete Konten und Kostenstellen-Filter.
 */
class BwaReportTest extends AccountingLedgerTestCase {
    private CarbonImmutable $jan;

    protected function setUp(): void {
        parent::setUp();
        $this->jan = CarbonImmutable::create(2026, 1, 1);

        // Vorjahr (Januar 2025) und Vormonat (Dezember 2025).
        $this->book('revenue', '500.00', CarbonImmutable::create(2025, 1, 15));
        $this->book('revenue', '800.00', CarbonImmutable::create(2025, 12, 10));
        $this->book('wages', '300.00', CarbonImmutable::create(2025, 12, 20));

        // Berichtsmonat Januar 2026.
        $this->book('revenue', '1000.00', $this->jan->addDays(5));
        $this->book('material', '100.00', $this->jan->addDays(6));
        $this->book('wages', '300.00', $this->jan->addDays(7));
        $this->book('rent', '200.00', $this->jan->addDays(8));
        $this->book('vehicle', '50.00', $this->jan->addDays(9));
        $this->book('depreciation', '40.00', $this->jan->addDays(10));
        $this->book('custom', '25.00', $this->jan->addDays(11));
    }

    /** @return array<string, mixed> */
    private function bwa(string $compare = BwaBuilder::COMPARE_NONE, ?int $costCenterId = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        return app(BwaBuilder::class)->build($this->org, $from ?? $this->jan, $to ?? $this->jan->endOfMonth()->startOfDay(), $compare, $costCenterId);
    }

    /** @param array<string, mixed> $data */
    private function row(array $data, string $key): array {
        foreach ($data['rows'] as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }
        $this->fail('Zeile fehlt: ' . $key);
    }

    public function test_groups_follow_the_skr03_number_ranges_and_subtotals_reconcile(): void {
        $data = $this->bwa();

        $this->assertSame(BwaAccountMapper::SCHEME_SKR03, $data['scheme']);
        $this->assertSame('1000.00', $data['groups'][BwaGroup::Revenue->value]['actual']);
        $this->assertSame('100.00', $data['groups'][BwaGroup::Material->value]['actual']);
        $this->assertSame('300.00', $data['groups'][BwaGroup::Personnel->value]['actual']);
        $this->assertSame('200.00', $data['groups'][BwaGroup::Premises->value]['actual']);
        $this->assertSame('50.00', $data['groups'][BwaGroup::Vehicle->value]['actual']);
        // Abschreibung über die EÜR-Kategorie — die Kategorisierung schlägt den Nummernkreis.
        $this->assertSame('40.00', $data['groups'][BwaGroup::Depreciation->value]['actual']);

        $this->assertSame('1000.00', $data['subtotals']['total_output']['actual']);
        $this->assertSame('900.00', $data['subtotals']['gross_profit']['actual']);
        $this->assertSame('590.00', $data['subtotals']['total_costs']['actual']);
        $this->assertSame('310.00', $data['subtotals']['operating_result']['actual']);
        $this->assertSame('310.00', $data['subtotals']['result_before_tax']['actual']);
    }

    /** Ein Konto ohne Zuordnung verschwindet nicht — es steht sichtbar unten und in der Schlusszeile. */
    public function test_unmapped_accounts_are_shown_and_reconcile_with_the_profit_and_loss(): void {
        $data = $this->bwa();

        $this->assertSame(1, $data['unmapped_count']);
        $unmapped = $this->row($data, 'unmapped:' . $this->accounts['custom']->id);
        $this->assertSame('25.00', $unmapped['values']['actual']);
        $this->assertSame('-25.00', $data['subtotals']['unmapped_net']['actual']);
        $this->assertSame('285.00', $data['subtotals']['result_total']['actual']);

        $pnl = app(ProfitAndLossBuilder::class)->build($this->org, $this->jan, $this->jan->endOfMonth()->startOfDay());
        $this->assertSame($pnl['result'], $data['subtotals']['result_total']['actual']);
    }

    public function test_explicit_bwa_group_on_the_account_wins_over_the_number_range(): void {
        $this->accounts['custom']->update(['bwa_group' => BwaGroup::OtherCosts]);

        $data = $this->bwa();

        $this->assertSame(0, $data['unmapped_count']);
        $this->assertSame('25.00', $data['groups'][BwaGroup::OtherCosts->value]['actual']);
        $this->assertSame('285.00', $data['subtotals']['result_before_tax']['actual']);
    }

    public function test_previous_year_comparison_carries_delta_and_percent(): void {
        $data = $this->bwa(BwaBuilder::COMPARE_PREVIOUS_YEAR);

        $this->assertSame([CarbonImmutable::create(2025, 1, 1)->toDateString(), CarbonImmutable::create(2025, 1, 31)->toDateString()], [
            $data['compare_range'][0]->toDateString(),
            $data['compare_range'][1]->toDateString(),
        ]);
        $revenue = $this->row($data, BwaGroup::Revenue->value);
        $this->assertSame('1000.00', $revenue['values']['actual']);
        $this->assertSame('500.00', $revenue['values']['compare']);
        $this->assertSame('500.00', $revenue['delta']);
        $this->assertSame('100.0', $revenue['delta_pct']);
        // Ohne Vergleichswert gibt es keinen Prozentsatz.
        $this->assertNull($this->row($data, BwaGroup::Personnel->value)['delta_pct']);
    }

    public function test_previous_month_comparison_keeps_month_boundaries(): void {
        $data = $this->bwa(BwaBuilder::COMPARE_PREVIOUS_MONTH);

        $this->assertSame('2025-12-01', $data['compare_range'][0]->toDateString());
        $this->assertSame('2025-12-31', $data['compare_range'][1]->toDateString());
        $revenue = $this->row($data, BwaGroup::Revenue->value);
        $this->assertSame('800.00', $revenue['values']['compare']);
        $this->assertSame('200.00', $revenue['delta']);
        $this->assertSame('25.0', $revenue['delta_pct']);
        $this->assertSame('0.00', $this->row($data, BwaGroup::Personnel->value)['delta']);
    }

    public function test_monthly_grid_has_twelve_months_and_a_total(): void {
        $data = $this->bwa(BwaBuilder::COMPARE_MONTHS);

        $keys = array_column($data['columns'], 'key');
        $this->assertCount(13, $keys);
        $this->assertSame('2026-01', $keys[0]);
        $this->assertSame('2026-12', $keys[11]);
        $this->assertSame(BwaBuilder::COL_TOTAL, $keys[12]);

        $revenue = $data['groups'][BwaGroup::Revenue->value];
        $this->assertSame('1000.00', $revenue['2026-01']);
        $this->assertSame('0.00', $revenue['2026-02']);
        $this->assertSame('1000.00', $revenue['total']);
        $this->assertSame('310.00', $data['subtotals']['operating_result']['total']);
    }

    public function test_budget_comparison_shows_plan_and_variance(): void {
        $budgets = app(AccountingBudgetService::class);
        $budgets->save($this->org, $this->accounts['revenue'], 2026, null, ['mode' => 'year', 'year_amount' => '12000.00'], $this->admin);
        $budgets->save($this->org, $this->accounts['wages'], 2026, null, ['mode' => 'months', 'months' => [1 => '250,00', 2 => '250,00']], $this->admin);

        $data = $this->bwa(BwaBuilder::COMPARE_BUDGET);

        $revenue = $this->row($data, BwaGroup::Revenue->value);
        $this->assertSame('1000.00', $revenue['values']['compare'], 'Jahreswert 12.000 → 1.000 je Monat');
        $this->assertSame('0.00', $revenue['delta']);
        $wages = $this->row($data, BwaGroup::Personnel->value);
        $this->assertSame('250.00', $wages['values']['compare']);
        $this->assertSame('50.00', $wages['delta']);
        $this->assertSame('20.0', $wages['delta_pct']);
        $this->assertSame('1000.00', $data['subtotals']['total_output']['compare']);
    }

    public function test_cost_center_filter_counts_only_lines_of_that_cost_center(): void {
        $centerA = $this->costCenter('A');
        $centerB = $this->costCenter('B');
        $this->book('revenue', '70.00', $this->jan->addDays(12), $centerA->id);
        $this->book('revenue', '30.00', $this->jan->addDays(13), $centerB->id);

        $all = $this->bwa();
        $onlyA = $this->bwa(BwaBuilder::COMPARE_NONE, $centerA->id);

        $this->assertSame('1100.00', $all['groups'][BwaGroup::Revenue->value]['actual']);
        $this->assertSame('70.00', $onlyA['groups'][BwaGroup::Revenue->value]['actual']);
        $this->assertSame('0.00', $onlyA['groups'][BwaGroup::Personnel->value]['actual']);
    }

    public function test_profit_and_loss_shares_the_comparison_columns(): void {
        $data = app(ProfitAndLossBuilder::class)->build($this->org, $this->jan, $this->jan->endOfMonth()->startOfDay(), ProfitAndLossBuilder::COMPARE_PREVIOUS_YEAR);

        $this->assertNotNull($data['compare_totals']);
        $this->assertSame('500.00', $data['compare_totals']['income_total']);
        $this->assertSame('500.00', $data['compare_totals']['result']);
        $revenue = collect($data['income'])->firstWhere('account.id', $this->accounts['revenue']->id);
        $this->assertSame('500.00', $revenue['compare']);
        $this->assertSame('100.0', $revenue['delta_pct']);
    }

    public function test_mapper_detects_skr04_from_the_revenue_range(): void {
        $mapper = new BwaAccountMapper;
        $accounts = [
            new AccountingAccount(['number' => '4400', 'type' => AccountType::Income]),
            new AccountingAccount(['number' => '6000', 'type' => AccountType::Expense]),
            new AccountingAccount(['number' => '6310', 'type' => AccountType::Expense]),
            new AccountingAccount(['number' => '7300', 'type' => AccountType::Expense]),
        ];

        $scheme = $mapper->detectScheme($accounts);

        $this->assertSame(BwaAccountMapper::SCHEME_SKR04, $scheme);
        $this->assertSame(BwaGroup::Revenue, $mapper->groupFor($accounts[0], $scheme));
        $this->assertSame(BwaGroup::Personnel, $mapper->groupFor($accounts[1], $scheme));
        $this->assertSame(BwaGroup::Premises, $mapper->groupFor($accounts[2], $scheme));
        $this->assertSame(BwaGroup::InterestExpense, $mapper->groupFor($accounts[3], $scheme));
        $this->assertNull($mapper->groupFor(new AccountingAccount(['number' => 'XY', 'type' => AccountType::Expense]), $scheme));
    }

    public function test_page_filters_and_exports_respond(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('reports.accounting.bwa'))->assertForbidden();

        $params = ['from' => '2026-01-01', 'to' => '2026-01-31'];
        foreach ([
            [],
            ['compare' => 'previous_year'],
            ['compare' => 'previous_month'],
            ['compare' => 'months'],
            ['compare' => 'budget'],
            ['cost_center' => $this->costCenter('C')->sqid],
        ] as $extra) {
            $this->actingAs($this->admin)->get(route('reports.accounting.bwa', $params + $extra))
                ->assertOk()
                ->assertSee(BwaGroup::Revenue->label());
        }

        $csv = $this->actingAs($this->admin)->get(route('reports.accounting.bwa', $params + ['export' => 'csv']))->assertOk();
        $this->assertStringContainsString('1000.00', (string) $csv->getContent());
        $this->assertStringContainsString(__('accounting.bwa.subtotal.result_total'), (string) $csv->getContent());
        $this->actingAs($this->admin)->get(route('reports.accounting.bwa', $params + ['export' => 'xlsx']))->assertOk();
        $this->actingAs($this->admin)->get(route('reports.accounting.bwa', $params + ['export' => 'pdf']))->assertOk();
        $this->actingAs($this->admin)->get(route('reports.accounting.profit-and-loss', ['compare' => 'previous_year']))->assertOk();
    }
}
