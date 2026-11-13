<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualIssueValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{NegativeStockRequestStatus, OwnershipType, ScanAction};
use App\Models\Approval\Approval;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{NegativeStockRequest, StockMovement, StockValuation, StockValuationLayer, Warehouse, WarehouseBin};
use App\Models\Platform\User;
use App\Services\Inventory\{FifoValuationService, InventoryLedger, NegativeStockApproval, ScanActionService, StockIssue, ValuationService};
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1692: Die Entnahme im Lager (Formular und Scan) bewertet Eigenbestand
 * nach dem Verfahren der Variante wie Auslieferung und Materialverbrauch —
 * FIFO verbraucht Schichten, der Durchschnitt mindert sein Buch. Fremdbestand
 * bleibt unbewertet; Negativbestand und Vier-Augen-Weg bleiben.
 */
final class ManualIssueValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $teamlead;

    private Warehouse $warehouse;

    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'DOSE-1',
        ]);
    }

    private function useFifo(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function issue(array $overrides = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), array_merge([
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'movement' => 'issue',
            'qty' => '7',
            'ownership' => OwnershipType::Own->value,
        ], $overrides));
    }

    private function remainingLayers(): string {
        return bcadd((string) StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->sum('qty_remaining'), '0', 4);
    }

    public function test_fifo_issue_consumes_the_oldest_layers(): void {
        $this->useFifo();
        $fifo = app(FifoValuationService::class);
        $fifo->receipt($this->variant, $this->warehouse, '5', '2.00');
        $fifo->receipt($this->variant, $this->warehouse, '5', '4.00');

        $this->issue()->assertSessionHas('success', __('inventory.flash.movement_posted'));

        $movement = StockMovement::query()->where('article_variant_id', $this->variant->id)->latest('id')->firstOrFail();
        $this->assertSame(['-7.0000', '18.0000'], [(string) $movement->qty_base, $movement->cost_total?->getAmount()], '5 × 2,00 + 2 × 4,00');
        $this->assertSame('3.0000', $this->remainingLayers());
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists(), 'Das Durchschnittsbuch bleibt bei FIFO still.');
    }

    public function test_moving_average_issue_is_valued_and_reduces_the_book(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '3.00');

        $this->issue(['qty' => '4'])->assertSessionHasNoErrors();

        $movement = StockMovement::query()->where('article_variant_id', $this->variant->id)->latest('id')->firstOrFail();
        $this->assertSame(['3.0000', '12.0000'], [$movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount()]);
        $book = StockValuation::query()->where('article_variant_id', $this->variant->id)->sole();
        $this->assertSame(['6.0000', '3.0000'], [bcadd((string) $book->qty_on_hand, '0', 4), $book->avg_cost?->getAmount()]);
    }

    public function test_foreign_stock_stays_unvalued_and_bins_are_kept(): void {
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '5', OwnershipType::Consignment);
        $this->issue(['qty' => '2', 'ownership' => OwnershipType::Consignment->value])->assertSessionHasNoErrors();
        $consignment = StockMovement::query()->where('ownership_type', OwnershipType::Consignment->value)->latest('id')->firstOrFail();
        $this->assertSame(['-2.0000', null], [(string) $consignment->qty_base, $consignment->cost_total]);

        // Eigenbestand auf einem Platz: bewertet, der Platz bleibt an der Bewegung.
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R1']);
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '4', '2.50');
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '4', bin: $bin);
        $this->issue(['qty' => '1', 'bin' => $bin->sqid])->assertSessionHasNoErrors();
        $own = StockMovement::query()->where('ownership_type', OwnershipType::Own->value)->where('movement_type', 'issue')->latest('id')->firstOrFail();
        $this->assertSame([(int) $bin->id, '2.5000'], [(int) $own->bin_id, $own->cost_unit?->getAmount()]);
    }

    public function test_four_eyes_approval_books_the_valued_issue(): void {
        $this->useFifo();
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '2', '5.00');
        Setting::set(NegativeStockApproval::SETTING, true, SettingScope::Organization, $this->organization);
        $this->organization->refresh();

        $this->issue(['qty' => '3', 'allow_negative' => 1])->assertSessionHas('success', __('inventory.negative.requested'));
        $request = NegativeStockRequest::query()->sole();
        $step = Approval::query()->where('approvable_id', $request->id)->where('approvable_type', $request->getMorphClass())->sole();
        $second = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($second)->post(route('approvals.decide', $step), ['decision' => 'approved'])->assertRedirect(route('approvals.index'));

        $this->assertSame(NegativeStockRequestStatus::Approved, $request->fresh()?->status);
        $movement = StockMovement::query()->where('article_variant_id', $this->variant->id)->where('movement_type', 'issue')->sole();
        $this->assertSame(['-3.0000', '15.0000'], [(string) $movement->qty_base, $movement->cost_total?->getAmount()], 'Die Schicht und der Rest zum letzten Schichtpreis.');
        $this->assertSame('0.0000', $this->remainingLayers());
    }

    public function test_scan_issue_is_valued_too(): void {
        $this->useFifo();
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '4', '1.50');

        $issue = app(ScanActionService::class)->book('DOSE-1', ScanAction::Issue, $this->warehouse, '3');

        $this->assertInstanceOf(StockIssue::class, $issue);
        $this->assertSame('4.5000', $issue->costTotal());
        $this->assertSame('1.0000', $this->remainingLayers());
    }
}
