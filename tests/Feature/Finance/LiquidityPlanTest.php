<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityPlanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{LiquidityPlanRecurrence, ProfitDetermination, TransactionDirection};
use App\Models\Finance\{BankAccount, BankStatement, BankTransaction, LiquidityForecastSnapshot, LiquidityPlanItem};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, FiscalYearService, LiquiditySnapshotService};
use App\Services\Accounting\Reports\LiquidityForecastBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-984: Planpositionen in der Liquiditätsvorschau, festgehaltener Wochenstand und Plan/Ist. */
final class LiquidityPlanTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    /** Mittwoch, 4. März 2026 — die erste Vorschauwoche beginnt am Montag, 2. März. */
    private CarbonImmutable $today;

    protected function setUp(): void {
        parent::setUp();
        $this->today = CarbonImmutable::create(2026, 3, 4, 9, 0, 0, 'UTC');
        $this->travelTo($this->today);

        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
    }

    private function plan(string $label, string $direction, string $amount, string $startsOn, LiquidityPlanRecurrence $recurrence, ?string $endsOn = null): LiquidityPlanItem {
        return LiquidityPlanItem::query()->create([
            'organization_id' => $this->org->id, 'label' => $label, 'direction' => $direction, 'planned_amount' => $amount,
            'currency' => 'EUR', 'starts_on' => $startsOn, 'recurrence' => $recurrence, 'ends_on' => $endsOn,
        ]);
    }

    private function bankTransaction(string $bookedOn, string $amount, TransactionDirection $direction): void {
        BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->org->id])->id,
            'booking_date' => $bookedOn, 'valuta_date' => $bookedOn, 'amount' => $amount, 'direction' => $direction, 'currency' => 'EUR',
        ]);
    }

    public function test_plan_items_fall_into_their_weeks_once_or_monthly_until_the_end(): void {
        $this->plan('Einlage Gesellschafter', 'in', '5000.00', '2026-03-18', LiquidityPlanRecurrence::Once);
        $this->plan('Kreditrate', 'out', '300.00', '2026-02-10', LiquidityPlanRecurrence::Monthly, '2026-04-30');
        $this->plan('Vergangen', 'out', '99.00', '2026-02-01', LiquidityPlanRecurrence::Once);

        $data = app(LiquidityForecastBuilder::class)->build($this->org, $this->today);

        $this->assertSame('5000.00', $data['buckets'][2]['sources']['plan']['in']);
        $this->assertSame('300.00', $data['buckets'][1]['sources']['plan']['out'], '10.03. in KW 11');
        $this->assertSame('300.00', $data['buckets'][5]['sources']['plan']['out'], '10.04. in KW 15');
        $this->assertSame('0.00', $data['buckets'][9]['sources']['plan']['out'], 'Ende 30.04.: keine Rate im Mai');
        $this->assertSame(3, $data['totals']['items']);
    }

    public function test_snapshot_is_compared_with_the_actual_bank_movements(): void {
        $this->plan('Kreditrate', 'out', '300.00', '2026-03-10', LiquidityPlanRecurrence::Once);
        $service = app(LiquiditySnapshotService::class);
        $snapshot = $service->take($this->org, $this->today);
        $this->assertSame('2026-03-02', $snapshot->taken_on->toDateString());
        $this->assertSame($snapshot->id, $service->take($this->org, $this->today->addDay())->id, 'dieselbe Woche überschreibt');

        $this->bankTransaction('2026-03-05', '500.00', TransactionDirection::Credit);
        $this->bankTransaction('2026-03-11', '200.00', TransactionDirection::Debit);
        $rows = $service->compare($snapshot->fresh(), CarbonImmutable::create(2026, 3, 12));

        $this->assertSame('500.00', $rows[0]['actual_in']?->getAmount());
        $this->assertSame('-300.00', $rows[1]['planned_net']->getAmount());
        $this->assertSame('-200.00', $rows[1]['actual_net']?->getAmount());
        $this->assertSame('100.00', $rows[1]['deviation']?->getAmount());
        $this->assertNull($rows[2]['actual_net'], 'Zukunftswoche ohne Ist');
    }

    public function test_command_takes_snapshots_for_organizations_with_a_bank_account(): void {
        BankAccount::factory()->create(['organization_id' => $this->org->id]);
        $other = Organization::factory()->create();

        $this->artisan('accounting:liquidity-snapshot')->assertSuccessful();

        $this->assertSame(1, LiquidityForecastSnapshot::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->count());
        $this->assertSame(0, LiquidityForecastSnapshot::query()->withoutGlobalScopes()->where('organization_id', $other->id)->count());
    }

    public function test_pages_are_permission_gated_and_plan_items_are_managed(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('reports.accounting.liquidity-plan.index'))->assertForbidden();
        $this->actingAs($member)->post(route('reports.accounting.liquidity-plan.store'), [])->assertForbidden();

        $this->actingAs($this->admin)->post(route('reports.accounting.liquidity-plan.store'), [
            'label' => 'USt-Vorauszahlung', 'direction' => 'out', 'planned_amount' => '1.250,50', 'starts_on' => '2026-03-10',
            'recurrence' => 'monthly', 'ends_on' => '2026-01-01',
        ])->assertSessionHasErrors(['planned_amount', 'ends_on']);
        $this->actingAs($this->admin)->post(route('reports.accounting.liquidity-plan.store'), [
            'label' => 'USt-Vorauszahlung', 'direction' => 'out', 'planned_amount' => '1250.50', 'starts_on' => '2026-03-10', 'recurrence' => 'monthly',
        ])->assertRedirect(route('reports.accounting.liquidity-plan.index'));
        $item = LiquidityPlanItem::query()->sole();
        $this->assertSame('1250.50', $item->planned_amount->getAmount());
        $this->assertSame(CurrencyCode::Euro, $item->currency);

        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-plan.index'))->assertOk()->assertSee('USt-Vorauszahlung');
        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-plan.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('reports.accounting.liquidity-plan.snapshot'))->assertRedirect();
        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-plan.actual'))->assertOk()->assertSee('KW');
        $this->actingAs($this->admin)->get(route('reports.accounting.liquidity-forecast'))->assertOk()->assertSee('USt-Vorauszahlung');

        $this->actingAs($this->admin)->delete(route('reports.accounting.liquidity-plan.destroy', $item))->assertRedirect();
        $this->assertSame(0, LiquidityPlanItem::query()->count());
    }
}
