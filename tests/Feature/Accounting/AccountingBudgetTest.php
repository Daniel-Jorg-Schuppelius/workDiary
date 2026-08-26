<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBudgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingBudget;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingBudgetService, FiscalCalendar};
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Konto-Budgets (Feature 142, MVP-709): Jahres-/Monatswerte, Verteilung,
 * Unique-Sentinel, Mandantentrennung, Vorjahr-Kopie und HTTP-Pflege.
 */
class AccountingBudgetTest extends AccountingLedgerTestCase {
    private function service(): AccountingBudgetService {
        return app(AccountingBudgetService::class);
    }

    public function test_year_value_is_spread_evenly_over_the_months(): void {
        $this->service()->save($this->org, $this->accounts['revenue'], 2026, null, ['mode' => 'year', 'year_amount' => '12001,00'], $this->admin);

        $january = $this->service()->plannedByAccount($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 1, 31));
        $quarter = $this->service()->plannedByAccount($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 3, 31));
        $year = $this->service()->plannedByAccount($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 12, 31));

        $id = $this->accounts['revenue']->id;
        $this->assertSame('1000.09', $january[$id], 'Rest-Cents liegen deterministisch vorn (4 × 1000,09, 8 × 1000,08)');
        $this->assertSame('3000.27', $quarter[$id]);
        $this->assertSame('12001.00', $year[$id], 'Verteilung geht ohne Rundungsverlust auf');
    }

    public function test_month_values_replace_a_year_value(): void {
        $this->service()->save($this->org, $this->accounts['wages'], 2026, null, ['mode' => 'year', 'year_amount' => '1200.00'], $this->admin);
        $this->service()->save($this->org, $this->accounts['wages'], 2026, null, ['mode' => 'months', 'months' => [1 => '100.00', 3 => '50.00', 13 => '999.00']], $this->admin);

        $rows = AccountingBudget::query()->where('accounting_account_id', $this->accounts['wages']->id)->orderBy('month')->get();
        $this->assertSame([1, 3], $rows->pluck('month')->all(), 'Jahreswert weg, ungültiger Monat 13 verworfen');

        $matrix = $this->service()->matrix($this->org, 2026);
        $row = collect($matrix['rows'])->firstWhere('account.id', $this->accounts['wages']->id);
        $this->assertSame(AccountingBudgetService::MODE_MONTHS, $row['mode']);
        $this->assertNull($row['year']);
        $this->assertSame('100.00', $row['months'][1]);
        $this->assertNull($row['months'][2]);
        $this->assertSame('150.00', $row['total']);
    }

    /** MySQL zählt NULL im Unique als verschieden — der Sentinel `cost_center_key` schließt das. */
    public function test_unique_index_holds_without_cost_center_and_month(): void {
        $attributes = [
            'organization_id' => $this->org->id,
            'fiscal_year' => 2026,
            'accounting_account_id' => $this->accounts['revenue']->id,
            'cost_center_id' => null,
            'month' => 0,
            'amount' => '10.00',
            'currency' => 'EUR',
        ];
        $first = AccountingBudget::query()->create($attributes);
        $this->assertSame(0, (int) $first->getAttribute('cost_center_key'));

        // Mit Kostenstelle ist es ein anderer Satz …
        $center = $this->costCenter('K1');
        $withCenter = AccountingBudget::query()->create(['cost_center_id' => $center->id] + $attributes);
        $this->assertSame($center->id, (int) $withCenter->getAttribute('cost_center_key'));

        // … derselbe Schlüssel ohne Kostenstelle aber nicht.
        $this->expectException(UniqueConstraintViolationException::class);
        AccountingBudget::query()->create($attributes);
    }

    public function test_budgets_are_tenant_scoped(): void {
        $other = Organization::factory()->create();
        $foreignCenter = $this->costCenter('FREMD', $other);
        AccountingBudget::query()->create([
            'organization_id' => $other->id,
            'fiscal_year' => 2026,
            'accounting_account_id' => $this->accounts['revenue']->id,
            'month' => 0,
            'amount' => '999.00',
            'currency' => 'EUR',
        ]);

        $planned = $this->service()->plannedByAccount($this->org, CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 12, 31));
        $this->assertArrayNotHasKey($this->accounts['revenue']->id, $planned);

        $this->expectException(ValidationException::class);
        $this->service()->save($this->org, $this->accounts['revenue'], 2026, $foreignCenter->id, ['mode' => 'year', 'year_amount' => '1.00'], $this->admin);
    }

    public function test_previous_year_actuals_become_month_budgets(): void {
        $this->book('revenue', '500.00', CarbonImmutable::create(2025, 1, 15));
        $this->book('revenue', '250.00', CarbonImmutable::create(2025, 6, 15));
        $this->book('wages', '300.00', CarbonImmutable::create(2025, 6, 20));
        // Das Zieljahr hatte schon ein Budget — es wird ersetzt.
        $this->service()->save($this->org, $this->accounts['revenue'], 2026, null, ['mode' => 'year', 'year_amount' => '1.00'], $this->admin);

        $count = $this->service()->copyPreviousYearActuals($this->org, 2026, null, $this->admin);

        $this->assertSame(2, $count);
        $revenue = AccountingBudget::query()->where('accounting_account_id', $this->accounts['revenue']->id)->where('fiscal_year', 2026)->orderBy('month')->get();
        $this->assertSame([1, 6], $revenue->pluck('month')->all());
        $this->assertSame('500.00', $revenue[0]->amount->getAmount());
        $this->assertSame('250.00', $revenue[1]->amount->getAmount());
        $wages = AccountingBudget::query()->where('accounting_account_id', $this->accounts['wages']->id)->where('fiscal_year', 2026)->get();
        $this->assertCount(1, $wages);
        $this->assertSame('300.00', $wages[0]->amount->getAmount());
    }

    public function test_fiscal_calendar_maps_a_shifted_fiscal_year(): void {
        $calendar = new FiscalCalendar;

        $this->assertSame(2026, $calendar->fiscalYearOf(CarbonImmutable::create(2026, 7, 1), 7));
        $this->assertSame(2026, $calendar->fiscalYearOf(CarbonImmutable::create(2027, 3, 1), 7));
        $this->assertSame(2025, $calendar->fiscalYearOf(CarbonImmutable::create(2026, 6, 30), 7));
        $this->assertSame(6, $calendar->positionOf(1, 7));
        $this->assertSame(0, $calendar->positionOf(7, 7));
        $months = $calendar->monthsOf(2026, 7);
        $this->assertSame('2026-07', $months[0]->format('Y-m'));
        $this->assertSame('2027-06', $months[11]->format('Y-m'));
    }

    public function test_budget_pages_are_permission_gated_and_editable_over_http(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('reports.accounting.budget.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('reports.accounting.budget.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee($this->accounts['revenue']->name);

        $this->actingAs($this->admin)
            ->get(route('reports.accounting.budget.edit', ['account' => $this->accounts['revenue']->sqid, 'year' => 2026]))
            ->assertOk()
            ->assertSee('name="fiscal_year"', false);

        $this->actingAs($this->admin)
            ->put(route('reports.accounting.budget.update', $this->accounts['revenue']), [
                'fiscal_year' => 2026,
                'cost_center' => '',
                'mode' => 'months',
                'months' => [1 => '1.500,50', 2 => '', 3 => '200'],
                'note' => 'Plan Q1',
            ])
            ->assertRedirect(route('reports.accounting.budget.index', ['year' => 2026]))
            ->assertSessionHas('status');

        $rows = AccountingBudget::query()->where('accounting_account_id', $this->accounts['revenue']->id)->orderBy('month')->get();
        $this->assertSame(['1500.50', '200.00'], $rows->map(fn (AccountingBudget $b): string => $b->amount->getAmount())->all());
        $this->assertSame('Plan Q1', $rows[0]->note);

        // Unbrauchbarer Betrag → 422, nichts geschrieben.
        $this->actingAs($this->admin)
            ->put(route('reports.accounting.budget.update', $this->accounts['revenue']), ['fiscal_year' => 2026, 'mode' => 'year', 'year_amount' => 'abc'])
            ->assertSessionHasErrors('year_amount');

        $this->actingAs($member)
            ->put(route('reports.accounting.budget.update', $this->accounts['revenue']), ['fiscal_year' => 2026, 'mode' => 'year', 'year_amount' => '1'])
            ->assertForbidden();

        $csv = $this->actingAs($this->admin)->get(route('reports.accounting.budget.index', ['year' => 2026, 'export' => 'csv']))->assertOk();
        $this->assertStringContainsString('1500.50', (string) $csv->getContent());
        $this->actingAs($this->admin)->get(route('reports.accounting.budget.index', ['year' => 2026, 'export' => 'xlsx']))->assertOk();
    }

    public function test_copy_previous_year_over_http(): void {
        $this->book('revenue', '500.00', CarbonImmutable::create(2025, 2, 15));

        $this->actingAs($this->admin)
            ->post(route('reports.accounting.budget.copy-previous-year', ['year' => 2026]))
            ->assertRedirect(route('reports.accounting.budget.index', ['year' => 2026]));

        $this->assertSame('500.00', AccountingBudget::query()->where('fiscal_year', 2026)->where('month', 2)->first()?->amount->getAmount());
    }
}
