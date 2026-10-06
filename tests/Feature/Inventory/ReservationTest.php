<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReservationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{ReservationStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, StockReservation, Warehouse};
use App\Models\Platform\Organization;
use App\Services\Inventory\{InventoryLedger, LotStockReader, ReservationService, StockLevelService, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Reservierungen als Entität + Mindest-/Meldebestand (Feature 048, MVP-068):
 * transaktionale Reservierung gegen Verfügbarkeit, keine Verdrängung älterer
 * Reservierungen, Teilreservierung, Erfüllung (reserviert → entnommen),
 * Freigabe und Beschaffungsbedarf unter Meldebestand.
 */
final class ReservationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private ReservationService $reservations;
    private StockLevelService $levels;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->ledger = app(InventoryLedger::class);
        $this->reservations = app(ReservationService::class);
        $this->levels = app(StockLevelService::class);
        $this->variant = $this->makeVariant();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_reserve_reduces_available_and_creates_entity(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $reservation = $this->reservations->reserve($this->variant, $this->warehouse, '4');

        $this->assertSame('6.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame(ReservationStatus::Active, $reservation->status);
        $this->assertSame('4.0000', $reservation->openQuantity());
    }

    public function test_younger_reservation_cannot_displace_older(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->reservations->reserve($this->variant, $this->warehouse, '7');

        $this->expectException(RuntimeException::class);
        $this->reservations->reserve($this->variant, $this->warehouse, '5');
    }

    public function test_reserve_up_to_available_takes_partial(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->reservations->reserve($this->variant, $this->warehouse, '7');

        $partial = $this->reservations->reserveUpToAvailable($this->variant, $this->warehouse, '5');
        $this->assertNotNull($partial);
        $this->assertSame('3.0000', $partial->openQuantity());
        $this->assertSame('0.0000', $this->ledger->available($this->variant, $this->warehouse));
    }

    public function test_fulfill_converts_reserved_to_issued(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $reservation = $this->reservations->reserve($this->variant, $this->warehouse, '6');

        $this->reservations->fulfill($reservation, '4');
        $this->assertSame('6.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('2.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Reserved));
        $this->assertSame('4.0000', $this->ledger->available($this->variant, $this->warehouse));

        $this->reservations->fulfill($reservation->fresh(), '2');
        $this->assertSame(ReservationStatus::Fulfilled, $reservation->fresh()->status);
        $this->assertSame('4.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    /** Die Reservierung bleibt chargenlos; ihre Erfüllung teilt nach FEFO Chargen zu. */
    public function test_fulfill_issues_per_lot_fefo(): void {
        $late = $this->lotWithStock('R-LATE', '2027-02-01', '6');
        $early = $this->lotWithStock('R-EARLY', '2026-11-01', '2');
        $reservation = $this->reservations->reserve($this->variant, $this->warehouse, '5');

        $this->reservations->fulfill($reservation, '5');

        $issued = StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->orderBy('id')->get();
        $this->assertSame([[$early->id, '-2.0000'], [$late->id, '-3.0000']], $issued->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base])->all());
        $this->assertSame(['0.0000', '3.0000'], [app(LotStockReader::class)->balanceOf($early), app(LotStockReader::class)->balanceOf($late)]);
        $this->assertSame(['0.0000', '3.0000'], [
            $this->ledger->balance($this->variant, $this->warehouse, StockState::Reserved),
            $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical),
        ]);
    }

    public function test_release_restores_availability(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $reservation = $this->reservations->reserve($this->variant, $this->warehouse, '6');

        $this->reservations->release($reservation);
        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame(ReservationStatus::Released, $reservation->fresh()->status);
    }

    public function test_below_reorder_detection(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '5');
        $this->levels->setLevels($this->variant, $this->warehouse, '3', '8');

        $below = $this->levels->belowReorder($this->warehouse);
        $this->assertCount(1, $below);
        $this->assertSame('5.0000', $below->first()['available']);
        $this->assertSame('3.0000', $below->first()['shortfall']);

        $this->ledger->receipt($this->variant, $this->warehouse, '5'); // available 10 ≥ reorder 8
        $this->assertCount(0, $this->levels->belowReorder($this->warehouse));
    }

    public function test_reservations_are_isolated_per_organization(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->reservations->reserve($this->variant, $this->warehouse, '2');
        $this->assertSame(1, StockReservation::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockReservation::query()->count());
    }

    private function lotWithStock(string $lotNo, string $bestBefore, string $qty): StockLot {
        $lot = StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $this->variant->id, 'lot_no' => $lotNo, 'best_before' => $bestBefore]);
        $this->ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, $qty, StockMovementType::Receipt, stockLotId: $lot->id));

        return $lot;
    }

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
