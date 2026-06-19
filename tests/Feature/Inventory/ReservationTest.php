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

use App\Enums\Inventory\{ReservationStatus, StockState};
use App\Models\{Article, ArticleVariant, Organization, StockReservation, Warehouse};
use App\Services\Inventory\{InventoryLedger, ReservationService, StockLevelService};
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

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
