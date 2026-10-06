<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotRepairCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryLedger, LotService, LotStockReader, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Reparaturbefehl `inventory:lots:repair` (Feature 048, E8.2): überhöhte
 * Chargensalden aus der Zeit, als Abgänge keine Charge trugen, werden unter
 * FIFO/FEFO nach „ohne Charge“ umgebucht — Probelauf ohne Buchung, `--apply`
 * mit Korrekturpaarung je Topf, physischer Bestand unverändert.
 */
final class LotRepairCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const HEADERS = ['Organisation', 'Charge', 'Buchsaldo', 'Schichtrest', 'Differenz', 'Ergebnis'];

    private InventoryLedger $ledger;
    private LotService $lots;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['valuation_method' => 'fefo']]);
        $this->ledger = app(InventoryLedger::class);
        $this->lots = app(LotService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = $this->makeVariant();
    }

    public function test_dry_run_lists_the_excess_and_books_nothing(): void {
        $lot = $this->legacyLot('L-ALT', '10', '4');
        $journal = StockMovement::query()->count();

        $this->artisan('inventory:lots:repair')
            ->expectsTable(self::HEADERS, [[$this->organization->name, 'L-ALT', '10.0000', '6.0000', '4.0000', 'würde nach „ohne Charge“ umgebucht']])
            ->expectsOutputToContain('Probelauf')
            ->assertSuccessful();

        $this->assertSame($journal, StockMovement::query()->count());
        $this->assertSame('10.0000', app(LotStockReader::class)->balanceOf($lot));
    }

    public function test_apply_detaches_the_excess_pot_by_pot_and_keeps_the_physical_stock(): void {
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R-01']);
        $lot = $this->lots->register($this->variant, 'L-ALT', '2026-12-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '4', '2', $lot);
        // Ohne Schicht gebucht: der Buchsaldo der Charge liegt 6 über der Restschicht.
        $this->ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '6.0000', StockMovementType::Receipt, stockLotId: $lot->id, bin: $bin));
        $before = [$this->ledger->balance($this->variant, $this->warehouse, StockState::Physical), $this->ledger->available($this->variant, $this->warehouse)];

        $this->artisan('inventory:lots:repair', ['--apply' => true])
            ->expectsTable(self::HEADERS, [[$this->organization->name, 'L-ALT', '10.0000', '4.0000', '6.0000', 'nach „ohne Charge“ umgebucht']])
            ->assertSuccessful();

        $key = 'lot-repair:' . $lot->id . ':';
        $this->assertSame(
            [
                [null, $lot->id, '-4.0000', $key . '1:out'],
                [null, null, '4.0000', $key . '1:in'],
                [$bin->id, $lot->id, '-2.0000', $key . '2:out'],
                [$bin->id, null, '2.0000', $key . '2:in'],
            ],
            $this->repairs()->map(fn (StockMovement $m): array => [$m->bin_id, $m->stock_lot_id, $m->qty_base, $m->idempotency_key])->all(),
        );
        foreach ($this->repairs() as $movement) {
            $this->assertSame([StockMovementType::Correction, StockState::Physical, null], [$movement->movement_type, $movement->stock_state, $movement->actor_user_id]);
        }
        $this->assertSame('4.0000', app(LotStockReader::class)->balanceOf($lot));
        $this->assertSame($before, [$this->ledger->balance($this->variant, $this->warehouse, StockState::Physical), $this->ledger->available($this->variant, $this->warehouse)]);
        $this->assertSame('6.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical, bin: $bin));

        // Ein zweiter Lauf findet nichts mehr.
        $this->artisan('inventory:lots:repair', ['--apply' => true])
            ->expectsOutput('Keine Abweichung zwischen Buchsaldo und Restschicht.')
            ->assertSuccessful();
        $this->assertCount(4, $this->repairs());
    }

    /** Gleitender Durchschnitt: die Schichten sinken nie — nur Meldung; gesperrte Chargen erst nach der Freigabe. */
    public function test_average_valuation_and_blocked_lots_are_only_reported(): void {
        $this->legacyLot('L-DURCHSCHNITT', '5', '2', $this->makeVariant(['valuation_method' => 'moving_average']));
        $held = $this->legacyLot('L-GESPERRT', '5', '2');
        $this->lots->block($held, 'Rückruf', User::factory()->create(['organization_id' => $this->organization->id]));
        $journal = StockMovement::query()->count();

        $this->artisan('inventory:lots:repair', ['--apply' => true])
            ->expectsTable(self::HEADERS, [
                [$this->organization->name, 'L-DURCHSCHNITT', '5.0000', '3.0000', '2.0000', 'gleitender Durchschnitt — nur Meldung'],
                [$this->organization->name, 'L-GESPERRT', '5.0000', '3.0000', '2.0000', 'gesperrt — erst freigeben'],
            ])
            ->assertSuccessful();

        $this->assertSame($journal, StockMovement::query()->count());
    }

    /** Stand vor den Abgängen je Charge: ein Abgang ohne Charge verbrauchte die Schicht, der Buchsaldo der Charge blieb. */
    private function legacyLot(string $lotNo, string $received, string $consumed, ?ArticleVariant $variant = null): StockLot {
        $variant ??= $this->variant;
        $lot = $this->lots->register($variant, $lotNo, '2026-12-01');
        $this->lots->receiveIntoLot($variant, $this->warehouse, $received, '2', $lot);
        $this->ledger->post(new StockPosting($variant, $this->warehouse, StockState::Physical, '-' . $consumed, StockMovementType::Issue));
        DB::table('stock_valuation_layers')->where('stock_lot_id', $lot->id)->update(['qty_remaining' => bcsub($received, $consumed, 4)]);

        return $lot;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, StockMovement> */
    private function repairs(): \Illuminate\Database\Eloquent\Collection {
        return StockMovement::query()->where('idempotency_key', 'like', 'lot-repair:%')->orderBy('id')->get();
    }

    /** @param array<string, mixed> $article */
    private function makeVariant(array $article = []): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true, ...$article]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
