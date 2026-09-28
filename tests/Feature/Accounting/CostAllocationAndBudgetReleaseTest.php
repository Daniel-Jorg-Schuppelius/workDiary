<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostAllocationAndBudgetReleaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\Finance\{BudgetReleaseStatus, BwaGroup};
use App\Services\Accounting\{AccountingBudgetService, CostAllocationService};
use App\Services\Accounting\Reports\BwaBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** MVP-982/983: Umlagen zwischen Kostenstellen in der BWA und Freigabe der Budgets. */
final class CostAllocationAndBudgetReleaseTest extends AccountingLedgerTestCase {
    public function test_bwa_after_allocation_moves_expense_shares_but_keeps_revenue(): void {
        $admin = $this->costCenter('VW');
        $shop = $this->costCenter('WS');
        $site = $this->costCenter('BS');
        $jan = CarbonImmutable::create(2026, 1, 1);
        $this->book('rent', '1000.00', $jan->addDays(3), $admin->id);
        $this->book('revenue', '500.00', $jan->addDays(4), $shop->id);
        $this->book('wages', '200.00', $jan->addDays(5), $shop->id);

        $service = app(CostAllocationService::class);
        $service->save($this->org, 2026, $admin, $shop, '60', $this->admin);
        $service->save($this->org, 2026, $admin, $site, '40', $this->admin);
        try {
            $service->save($this->org, 2026, $admin, $site, '50', $this->admin);
            $this->fail('Mehr als 100 % Umlage wurden angenommen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('share_percent', $e->errors());
        }

        $to = $jan->endOfMonth()->startOfDay();
        $shopRaw = app(BwaBuilder::class)->build($this->org, $jan, $to, BwaBuilder::COMPARE_NONE, $shop->id);
        $shopAllocated = app(BwaBuilder::class)->build($this->org, $jan, $to, BwaBuilder::COMPARE_NONE, $shop->id, true);
        $adminAllocated = app(BwaBuilder::class)->build($this->org, $jan, $to, BwaBuilder::COMPARE_NONE, $admin->id, true);

        $this->assertSame('0.00', $shopRaw['groups'][BwaGroup::Premises->value]['actual']);
        $this->assertSame('600.00', $shopAllocated['groups'][BwaGroup::Premises->value]['actual']);
        $this->assertSame('500.00', $shopAllocated['groups'][BwaGroup::Revenue->value]['actual']);
        $this->assertSame('0.00', $adminAllocated['groups'][BwaGroup::Premises->value]['actual']);

        $this->actingAs($this->admin)->get(route('reports.accounting.bwa', ['from' => '2026-01-01', 'to' => '2026-01-31', 'cost_center' => $shop->sqid, 'allocated' => 1]))->assertOk()
            ->assertSeeText(__('accounting.bwa.filter.allocated'));
        $this->actingAs($this->admin)->get(route('reports.accounting.allocations.index', ['year' => 2026]))->assertOk()->assertSeeText('Kostenstelle WS');
    }

    public function test_released_budget_is_locked_until_an_amendment_and_overruns_are_flagged(): void {
        $budgets = app(AccountingBudgetService::class);
        $budgets->save($this->org, $this->accounts['rent'], 2026, null, ['mode' => 'months', 'months' => [1 => '150.00']], $this->admin);

        $this->actingAs($this->admin)->post(route('reports.accounting.budget.release', ['year' => 2026]))->assertRedirect();
        $this->assertSame(BudgetReleaseStatus::Released, $budgets->releaseFor($this->org, 2026, null)?->status);
        $this->actingAs($this->admin)->get(route('reports.accounting.budget.index', ['year' => 2026]))->assertOk()->assertSeeText(__('accounting.budget.action.reopen'));

        try {
            $budgets->save($this->org, $this->accounts['rent'], 2026, null, ['mode' => 'months', 'months' => [1 => '999.00']], $this->admin);
            $this->fail('Ein freigegebenes Budget wurde geändert.');
        } catch (ValidationException $e) {
            $this->assertSame(__('accounting.budget.error.released'), $e->errors()['budget'][0] ?? null);
        }

        $entry = $this->book('rent', '200.00', CarbonImmutable::create(2026, 1, 20));
        $overruns = $budgets->overrunsFor($entry);
        $this->assertCount(1, $overruns);
        $this->assertSame('200.00', $overruns[0]['actual']);
        $this->actingAs($this->admin)->get(route('finance.accounting.journal.show', $entry))->assertOk()->assertSee('150,00');

        $this->actingAs($this->admin)->post(route('reports.accounting.budget.reopen', ['year' => 2026]), ['reopen_reason' => 'Mieterhöhung'])->assertRedirect();
        $release = $budgets->releaseFor($this->org, 2026, null);
        $this->assertSame(BudgetReleaseStatus::Draft, $release?->status);
        $this->assertSame('Mieterhöhung', $release?->reopen_reason);
        $budgets->save($this->org, $this->accounts['rent'], 2026, null, ['mode' => 'months', 'months' => [1 => '250.00']], $this->admin);
        $this->assertSame([], $budgets->overrunsFor($entry));
    }
}
