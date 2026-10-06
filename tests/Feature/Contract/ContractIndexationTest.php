<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractIndexationStatus, ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod, PriceIndexStatus};
use App\Models\Contract\{Contract, ContractIndexation, PriceIndexValue};
use App\Models\Platform\User;
use App\Services\Contract\{ContractIndexationService, ContractService, PriceIndexService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-952: Verbraucherpreisindex mit Freigabe und Indexanpassung am Vertrag. */
final class ContractIndexationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CSV = <<<'CSV'
        "";BBDP1.M.DE.N.VPI.C.A00000.I20.A;BBDP1.M.DE.N.VPI.C.A00000.I20.A_FLAGS
        "";Verbraucherpreisindex / Deutschland / 2020 = 100;
        Einheit;2020 = 100;
        2024-01;117,6;
        2026-05;124,2;
        2026-06;124,6;
        CSV;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function contract(array $overrides = []): Contract {
        $contract = app(ContractService::class)->create($this->organization, $this->admin, [
            'title' => 'Hallenmiete', 'kind' => ContractKind::Rent->value, 'partner_type' => ContractPartnerType::Other->value, 'partner_name' => 'Vermieter',
            'term_kind' => ContractTermKind::OpenEnded->value, 'starts_on' => '2024-01-01', 'indexation_method' => IndexationMethod::ConsumerPriceIndex->value,
            'currency' => 'EUR', 'value_amount' => 1000, 'value_period' => 'monthly', 'responsible_user_id' => $this->admin->id,
        ]);
        $contract->forceFill(['status' => ContractStatus::Active->value, 'indexation_base_value' => '117.6', 'indexation_base_period_on' => '2024-01-01'] + $overrides)->save();

        return $contract->fresh();
    }

    private function approveAll(): void {
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        foreach (PriceIndexValue::query()->get() as $value) {
            app(PriceIndexService::class)->approve($value, $operator);
        }
    }

    public function test_import_waits_for_approval_and_revisions_fall_back_to_pending(): void {
        $index = app(PriceIndexService::class);
        $this->assertSame(3, $index->ingest(self::CSV));
        $this->assertSame(0, $index->ingest(self::CSV));
        $this->assertSame(3, PriceIndexValue::query()->where('status', PriceIndexStatus::Pending->value)->count());
        $this->assertNull($index->latestApproved(CarbonImmutable::parse('2026-07-01')));

        $this->approveAll();
        $this->assertSame('124.6', (string) $index->latestApproved(CarbonImmutable::parse('2026-07-01'))?->value);

        $this->assertSame(1, $index->ingest(str_replace('2026-06;124,6;', '2026-06;124,8;', self::CSV)));
        $this->assertSame('124.2', (string) $index->latestApproved(CarbonImmutable::parse('2026-07-01'))?->value);
    }

    public function test_sync_command_reads_the_bundesbank_series(): void {
        FakePluginHttp::fake(['https://api.statistiken.bundesbank.de/*' => FakePluginHttp::response(self::CSV)]);
        $this->artisan('contracts:price-index-sync')->expectsOutputToContain('3 Monatswerte')->assertSuccessful();
    }

    public function test_only_the_platform_operator_approves_values(): void {
        app(PriceIndexService::class)->ingest(self::CSV);
        $value = PriceIndexValue::query()->where('period_on', '2026-06-01')->sole();

        $this->actingAs($this->admin)->get(route('contracts.price-index.index'))->assertOk()->assertSeeText('06/2026');
        $this->actingAs($this->admin)->post(route('contracts.price-index.approve', $value))->assertForbidden();

        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($operator)->post(route('contracts.price-index.approve', $value))->assertSessionHas('success');
        $this->assertSame(PriceIndexStatus::Approved, $value->fresh()->status);
        $this->actingAs($operator)->post(route('contracts.price-index.store'), ['period' => '2026-07', 'value' => '125.0'])->assertSessionHas('success');
        $this->assertSame(PriceIndexStatus::Approved, PriceIndexValue::query()->where('period_on', '2026-07-01')->sole()->status);
    }

    /** Konsolidierungs-Audit 2026-10, k1-03: der Faktor wird nicht mehr bei sechs Stellen abgeschnitten. */
    public function test_large_contract_values_are_not_truncated(): void {
        app(PriceIndexService::class)->ingest(self::CSV);
        $this->approveAll();

        // 1.000.000 × 124,6 / 117,6 = 1.059.523,8095… — der abgeschnittene Faktor 1,059523 ergab 1.059.523,00.
        $result = app(ContractIndexationService::class)->calculate($this->contract(['value_amount' => '1000000.00']));

        $this->assertNotNull($result);
        $this->assertSame('1059523.81', $result['new_amount']);
        $this->assertSame('5.9524', $result['change_percent']);
    }

    public function test_proposal_threshold_pass_through_and_apply(): void {
        app(PriceIndexService::class)->ingest(self::CSV);
        $this->approveAll();
        $service = app(ContractIndexationService::class);

        // 124,6 / 117,6 = +5,9524 %; bei 50 % Weitergabe +2,9762 % → 1.029,76 €.
        $contract = $this->contract(['indexation_threshold_percent' => '5.00', 'indexation_pass_through_percent' => '50.00']);
        $indexation = $service->propose($contract);
        $this->assertNotNull($indexation);
        $this->assertSame('1029.76', (string) $indexation->new_amount);
        $this->assertSame('2.9762', (string) $indexation->change_percent);
        $this->assertNull($service->propose($contract->fresh()));

        $this->actingAs($this->admin)->get(route('contracts.show', $contract))->assertOk()->assertSeeText(__('contract.indexation.title'));
        $this->actingAs($this->admin)->post(route('contracts.indexation.apply', $indexation), ['effective_on' => '2026-08-01'])->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame('1029.76', (string) $contract->value_amount);
        $this->assertSame('124.6', (string) $contract->indexation_base_value);
        $this->assertSame('2026-06-01', $contract->indexation_base_period_on?->toDateString());
        $this->assertSame(ContractIndexationStatus::Applied, $indexation->fresh()->status);
        $this->assertNull($service->propose($contract));
    }

    public function test_threshold_blocks_small_changes(): void {
        app(PriceIndexService::class)->ingest(self::CSV);
        $this->approveAll();

        $contract = $this->contract(['indexation_threshold_percent' => '10.00']);
        $this->assertNull(app(ContractIndexationService::class)->propose($contract));
        $this->actingAs($this->admin)->post(route('contracts.indexation.propose', $contract))->assertSessionHas('success', __('contract.indexation.flash.none'));
    }

    public function test_scan_proposes_outside_a_request_and_dialog_saves_the_clause(): void {
        app(PriceIndexService::class)->ingest(self::CSV);
        $this->approveAll();
        $contract = $this->contract();

        $this->actingAs($this->admin)->put(route('contracts.indexation.update', $contract), ['indexation_base_value' => '120.0', 'indexation_base_period' => '2025-01', 'indexation_threshold_percent' => '', 'indexation_pass_through_percent' => ''])->assertSessionHas('success');
        $this->assertSame('120.0', (string) $contract->fresh()->indexation_base_value);

        auth()->logout();
        app(ContractIndexationService::class)->scan($this->organization);
        $this->assertSame(1, ContractIndexation::query()->withoutGlobalScopes()->where('contract_id', $contract->id)->where('status', ContractIndexationStatus::Proposed->value)->count());
    }
}
