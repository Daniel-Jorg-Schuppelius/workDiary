<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDisposalPostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, FixedAssetDisposalKind, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, FixedAsset};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, FixedAssetService};
use App\Services\Accounting\Posting\Adapters\AssetDisposalAdapter;
use App\Services\Accounting\Posting\PostingInboxService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-891: Restbuchwert beim Anlagenabgang als Buchung. */
class AssetDisposalPostingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        foreach ([
            'bga' => ['0410', 'Betriebs- und Geschäftsausstattung', AccountType::Asset, PostingAccountRole::FixedAsset],
            'loss' => ['2310', 'Anlagenabgänge (Buchverlust)', AccountType::Expense, PostingAccountRole::DisposalLoss],
            'gain' => ['2315', 'Anlagenabgänge (Buchgewinn)', AccountType::Income, PostingAccountRole::DisposalGain],
        ] as $key => [$number, $name, $type, $role]) {
            $this->accounts[$key] = $chart->create($this->org, ['number' => $number, 'name' => $name, 'type' => $type]);
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id,
                'source_kind' => PostingSourceKind::AssetDisposal,
                'role' => $role,
                'accounting_account_id' => $this->accounts[$key]->id,
                'priority' => 100,
                'version' => 1,
                'valid_from' => $startsOn->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    private function disposedPress(FixedAssetDisposalKind $kind, ?string $proceeds): FixedAsset {
        $service = app(FixedAssetService::class);
        $asset = $service->create($this->org, $this->admin, ['name' => 'Presse', 'acquired_on' => '2026-01-01', 'acquisition_cost' => '12000.00', 'residual_value' => '0.00', 'useful_life_months' => 60]);

        return $service->dispose($asset, CarbonImmutable::parse('2026-06-30'), $this->admin, null, $kind, $proceeds);
    }

    /** @return array<string, array{debit: string, credit: string}> */
    private function lines(FixedAsset $asset): array {
        $proposal = app(AssetDisposalAdapter::class)->proposalFor($this->org, $asset);
        $this->assertSame([], $proposal->blockers);
        $out = [];
        foreach ($proposal->lines as $line) {
            $out[$line->account->number] = ['debit' => $line->debit, 'credit' => $line->credit];
        }

        return $out;
    }

    public function test_sale_above_book_value_writes_residual_off_as_gain(): void {
        $asset = $this->disposedPress(FixedAssetDisposalKind::Sale, '11000.00');

        $this->assertSame('11000.00', $asset->disposal_proceeds_amount?->getAmount());
        $this->assertSame([
            '2315' => ['debit' => '10800.00', 'credit' => '0.00'],
            '0410' => ['debit' => '0.00', 'credit' => '10800.00'],
        ], $this->lines($asset));
    }

    public function test_scrapping_writes_residual_off_as_loss_and_reaches_the_inbox(): void {
        $asset = $this->disposedPress(FixedAssetDisposalKind::Scrap, null);

        $this->assertSame(['2310', '0410'], array_map('strval', array_keys($this->lines($asset))));

        $adapter = app(AssetDisposalAdapter::class);
        $candidates = $adapter->candidates($this->org, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
        $this->assertSame([$asset->id], $candidates->pluck('id')->all());

        $result = app(PostingInboxService::class)->processBatch($this->org, [['proposal' => $adapter->proposalFor($this->org, $asset)]], $this->admin, true);
        $this->assertSame(['prepared' => 1, 'posted' => 1, 'failed' => []], $result);
    }

    public function test_dispose_dialog_requires_proceeds_for_a_sale(): void {
        $asset = app(FixedAssetService::class)->create($this->org, $this->admin, ['name' => 'Stapler', 'acquired_on' => '2026-01-01', 'acquisition_cost' => '5000.00', 'residual_value' => '0.00', 'useful_life_months' => 60]);

        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.dispose-form', $asset))->assertOk()->assertSee('disposal_kind');
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.dispose', $asset), ['disposed_on' => '2026-05-31', 'disposal_kind' => 'sale'])
            ->assertSessionHasErrors('disposal_proceeds_amount');
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.dispose', $asset), ['disposed_on' => '2026-05-31', 'disposal_kind' => 'sale', 'disposal_proceeds_amount' => '3000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(FixedAssetDisposalKind::Sale, $asset->fresh()->disposal_kind);
    }
}
