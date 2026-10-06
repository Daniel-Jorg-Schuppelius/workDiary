<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryLedgerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{InventoryMode, OwnershipType, ProviderCapability, StockLotStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use App\Models\Platform\{Organization, User};
use App\Services\Inventory\{InventoryLedger, InventoryProviderResolver, LocalInventoryProvider, LotService, LotStockReader, StockIssue, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lokaler Lagerkern (Feature 048, MVP-066/067): abgeleitete Bestände aus dem
 * append-only Journal, Verfügbarkeitsformel, Negativsperre, getrennte
 * Eigentumsarten, Idempotenz, Unveränderlichkeit, Provider-Auflösung und
 * Mandantengrenze; Abgang je Charge (gewählt oder FEFO, Rest ohne Charge).
 */
final class InventoryLedgerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->ledger = app(InventoryLedger::class);
        $this->variant = $this->makeVariant();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_receipt_increases_available_and_physical_balance(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    public function test_issue_reduces_and_blocks_negative_unless_allowed(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->ledger->issue($this->variant, $this->warehouse, '4');
        $this->assertSame('6.0000', $this->ledger->available($this->variant, $this->warehouse));

        try {
            $this->ledger->issue($this->variant, $this->warehouse, '100');
            $this->fail('Abgang über den verfügbaren Bestand muss blockiert werden.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->ledger->issue($this->variant, $this->warehouse, '100', allowNegative: true);
        $this->assertSame('-94.0000', $this->ledger->available($this->variant, $this->warehouse));
    }

    public function test_reservation_reduces_available_and_release_restores(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->ledger->reserve($this->variant, $this->warehouse, '3');

        $this->assertSame('7.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('3.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Reserved));

        $this->ledger->releaseReservation($this->variant, $this->warehouse, '3');
        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
    }

    public function test_reserve_beyond_available_throws(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '2');

        $this->expectException(RuntimeException::class);
        $this->ledger->reserve($this->variant, $this->warehouse, '5');
    }

    public function test_ownership_buckets_are_separate(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10', OwnershipType::Own);
        $this->ledger->receipt($this->variant, $this->warehouse, '5', OwnershipType::Customer);

        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical, OwnershipType::Own));
        $this->assertSame('5.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical, OwnershipType::Customer));
    }

    public function test_movements_are_append_only(): void {
        $movement = $this->ledger->receipt($this->variant, $this->warehouse, '1');

        try {
            $movement->update(['qty_base' => '99']);
            $this->fail('Update einer Lagerbewegung muss blockiert sein.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->expectException(RuntimeException::class);
        $movement->delete();
    }

    public function test_idempotent_posting_prevents_double_booking(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10', idempotencyKey: 'wh-1');
        $this->ledger->receipt($this->variant, $this->warehouse, '10', idempotencyKey: 'wh-1');

        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame(1, StockMovement::query()->where('idempotency_key', 'wh-1')->count());
    }

    public function test_resolver_defaults_to_local_and_exposes_capabilities(): void {
        $resolver = app(InventoryProviderResolver::class);

        $this->assertSame(InventoryMode::Local, $resolver->modeFor($this->organization));
        $provider = $resolver->providerFor($this->organization);
        $this->assertInstanceOf(LocalInventoryProvider::class, $provider);
        $this->assertTrue($provider->supports(ProviderCapability::Reserve));
    }

    public function test_resolver_throws_for_external_mode_without_plugin(): void {
        $this->organization->update(['settings' => ['inventory_mode' => 'external']]);

        $this->expectException(RuntimeException::class);
        app(InventoryProviderResolver::class)->providerFor($this->organization->fresh());
    }

    public function test_movements_are_isolated_per_organization(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->assertSame(1, StockMovement::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockMovement::query()->count(), 'Fremd-Org sieht keine Bewegungen');
    }

    public function test_issue_without_lot_stock_books_one_movement_without_lot(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '4', idempotencyKey: 'plain-1');

        $this->assertCount(1, $issue);
        $this->assertSame([null, '-4.0000', 'plain-1'], [$issue->first()->stock_lot_id, $issue->first()->qty_base, $issue->first()->idempotency_key]);
        $this->assertSame(['4.0000', []], [$issue->qty(), $issue->lots()]);
    }

    public function test_issue_with_chosen_lot_books_that_lot(): void {
        $early = $this->lotWithStock('L-EARLY', '2026-05-01', '5');
        $late = $this->lotWithStock('L-LATE', '2026-09-01', '5');

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '3', lot: $late);

        $this->assertCount(1, $issue);
        $this->assertSame($late->id, $issue->first()->stock_lot_id);
        $this->assertSame(['5.0000', '2.0000'], [$this->stock()->balanceOf($early), $this->stock()->balanceOf($late)]);
    }

    /** FEFO über zwei Chargen und Rest ohne Charge: eine Bewegung je Teil, gesperrte Charge übersprungen. */
    public function test_issue_allocates_fefo_across_lots_and_books_the_rest_without_lot(): void {
        $late = $this->lotWithStock('L-LATE', '2026-09-01', '2');
        $none = $this->lotWithStock('L-NONE', null, '1');
        $early = $this->lotWithStock('L-EARLY', '2026-05-01', '3');
        $held = $this->lotWithStock('L-HELD', '2026-01-01', '4');
        DB::table('stock_lots')->where('id', $held->id)->update(['status' => StockLotStatus::Blocked->value]);
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '8');

        $this->assertSame(
            [[$early->id, '-3.0000'], [$late->id, '-2.0000'], [$none->id, '-1.0000'], [null, '-2.0000']],
            array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base], $issue->movements),
        );
        $this->assertSame(['L-EARLY', 'L-LATE', 'L-NONE'], array_map(fn (StockLot $lot): string => $lot->lot_no, $issue->lots()));
        $this->assertSame('4.0000', $this->stock()->balanceOf($held));
        $this->assertSame('12.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    /** Mit Platz zählt nur der Chargenbestand dieses Platzes. */
    public function test_issue_from_a_bin_allocates_only_the_lots_on_that_bin(): void {
        $near = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R1']);
        $far = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R2']);
        $early = $this->lotWithStock('L-EARLY', '2026-05-01', '3', $far);
        $late = $this->lotWithStock('L-LATE', '2026-09-01', '3', $near);

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '2', bin: $near);

        $this->assertSame([[$late->id, $near->id]], array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->bin_id], $issue->movements));
        $this->assertSame(['3.0000', '1.0000'], [$this->stock()->balanceOf($early), $this->stock()->balanceOf($late)]);
    }

    /** Altbestand einer zusammengeführten Charge zählt bei der Zielcharge — gebucht wird auf sie. */
    public function test_issue_books_the_target_of_a_merged_lot(): void {
        $target = $this->lotWithStock('L-TARGET', '2026-09-01', '2');
        $source = $this->lotWithStock('L-SOURCE', '2026-05-01', '3');
        DB::table('stock_lots')->where('id', $source->id)->update(['status' => StockLotStatus::Merged->value, 'merged_into_lot_id' => $target->id]);

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '4');

        $this->assertSame([[$target->id, '-4.0000']], array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base], $issue->movements));
        $this->assertSame(['1.0000', '0.0000'], [$this->stock()->balanceOf($target), $this->stock()->balanceOf($source->fresh() ?? $source)]);
    }

    /** Mehrere Teile tragen `<schlüssel>#n`; die Wiederholung liefert den früheren Abgang, auch wenn heute anders zugeteilt würde. */
    public function test_idempotency_key_per_part_and_replay(): void {
        $early = $this->lotWithStock('L-EARLY', '2026-05-01', '2');
        $late = $this->lotWithStock('L-LATE', '2026-09-01', '5');

        $first = $this->ledger->issue($this->variant, $this->warehouse, '4', idempotencyKey: 'pick-7');
        $this->assertSame(['pick-7#1', 'pick-7#2'], array_map(fn (StockMovement $m): ?string => $m->idempotency_key, $first->movements));

        $again = $this->ledger->issue($this->variant, $this->warehouse, '4', idempotencyKey: 'pick-7');

        $this->assertSame(array_map(fn (StockMovement $m): int => $m->id, $first->movements), array_map(fn (StockMovement $m): int => $m->id, $again->movements));
        $this->assertSame(2, StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->count());
        $this->assertSame(['0.0000', '3.0000'], [$this->stock()->balanceOf($early), $this->stock()->balanceOf($late)]);

        // Ein Teil behält den Schlüssel unverändert; die Wiederholung bucht nichts.
        $single = $this->ledger->issue($this->variant, $this->warehouse, '1', idempotencyKey: 'pick-8');
        $this->assertSame('pick-8', $single->first()->idempotency_key);
        $this->assertSame($single->first()->id, $this->ledger->issue($this->variant, $this->warehouse, '1', idempotencyKey: 'pick-8')->first()->id);
        $this->assertSame('2.0000', $this->stock()->balanceOf($late));
    }

    /** Ohne Schlüssel erhält ein mehrteiliger Abgang einen Gruppenschlüssel: jede seiner Bewegungen führt zum ganzen Abgang. */
    public function test_issue_of_finds_all_parts_of_a_multi_part_issue(): void {
        $this->lotWithStock('L-EARLY', '2026-05-01', '2');
        $this->lotWithStock('L-LATE', '2026-09-01', '5');
        $this->ledger->receipt($this->variant, $this->warehouse, '1');

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '4');
        $plain = $this->ledger->issue($this->variant, $this->warehouse, '1', lot: $issue->lots()[1]);

        $this->assertStringStartsWith(InventoryLedger::ISSUE_GROUP_PREFIX, (string) $issue->first()->idempotency_key);
        $ids = array_map(fn (StockMovement $m): int => $m->id, $issue->movements);
        $this->assertSame($ids, array_map(fn (StockMovement $m): int => $m->id, $this->ledger->issueOf($issue->movements[1]->fresh() ?? $issue->movements[1])->movements));
        $this->assertSame([$plain->first()->id], array_map(fn (StockMovement $m): int => $m->id, $this->ledger->issueOf($plain->first())->movements));
        $this->assertNull($plain->first()->idempotency_key);
    }

    public function test_chosen_lot_must_be_active_belong_to_the_variant_and_cover_the_quantity(): void {
        $lot = $this->lotWithStock('L-1', '2026-05-01', '2');
        $other = $this->makeVariant();
        $foreign = StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $other->id, 'lot_no' => 'L-X']);
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $cases = [
            'Menge' => [$lot, '3', __('inventory.lot.error.insufficient', ['lot' => 'L-1'])],
            'Variante' => [$foreign, '1', __('inventory.lot.error.foreign', ['lot' => 'L-X'])],
        ];
        foreach ($cases as $case => [$chosen, $qty, $message]) {
            try {
                $this->ledger->issue($this->variant, $this->warehouse, $qty, lot: $chosen);
                $this->fail('Abgang muss scheitern: ' . $case);
            } catch (RuntimeException $e) {
                $this->assertSame($message, $e->getMessage(), $case);
            }
        }

        DB::table('stock_lots')->where('id', $lot->id)->update(['status' => StockLotStatus::Blocked->value]);
        try {
            $this->ledger->issue($this->variant, $this->warehouse, '1', lot: $lot);
            $this->fail('Eine gesperrte Charge gibt nichts ab.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.lot.error.not_active', ['lot' => 'L-1']), $e->getMessage());
        }

        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->count());
    }

    /** Mit Negativfreigabe darf genau die gewählte Charge ins Minus — wie Bestand ohne Charge. */
    public function test_chosen_lot_may_go_negative_with_approval(): void {
        $lot = $this->lotWithStock('L-1', '2026-05-01', '2');
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '5', allowNegative: true, lot: $lot);

        $this->assertInstanceOf(StockIssue::class, $issue);
        $this->assertSame([[$lot->id, '-5.0000']], array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base], $issue->movements));
        $this->assertSame('-3.0000', $this->stock()->balanceOf($lot));
    }

    /** Die Sperrbuchung mindert die Verfügbarkeit überall — Prüfung des Abgangs eingeschlossen; die Freigabe stellt sie wieder her. */
    public function test_available_drops_by_the_blocked_lot_balance(): void {
        $lots = app(LotService::class);
        $actor = User::factory()->create(['organization_id' => $this->organization->id]);
        $held = $this->lotWithStock('L-HELD', '2026-05-01', '5');
        $this->ledger->receipt($this->variant, $this->warehouse, '5');
        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));

        $lots->block($held, 'Reklamation', $actor);

        $this->assertSame('5.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        try {
            $this->ledger->issue($this->variant, $this->warehouse, '6');
            $this->fail('Gesperrter Bestand ist nicht verfügbar.');
        } catch (RuntimeException) {
        }
        $issue = $this->ledger->issue($this->variant, $this->warehouse, '5');
        $this->assertSame([[null, '-5.0000']], array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base], $issue->movements));

        $lots->unblock($held, 'Prüfung ohne Befund', $actor);
        $this->assertSame('5.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('5.0000', $this->stock()->balanceOf($held));
    }

    /** Chargenpflicht: ergäbe die Zuteilung einen Teil ohne Charge, wird nichts gebucht. */
    public function test_require_lot_rejects_an_allocation_with_a_part_without_lot(): void {
        $lot = $this->lotWithStock('L-1', '2026-05-01', '2');
        $this->ledger->receipt($this->variant, $this->warehouse, '5');

        try {
            $this->ledger->issue($this->variant, $this->warehouse, '3', requireLot: true);
            $this->fail('Ein Teil ohne Charge muss abgewiesen werden.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.error.lot_required'), $e->getMessage());
        }
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->count());

        $issue = $this->ledger->issue($this->variant, $this->warehouse, '2', requireLot: true);
        $this->assertSame([[$lot->id, '-2.0000']], array_map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base], $issue->movements));
        // Eine gewählte Charge mit Negativfreigabe bleibt ein Teil mit Charge.
        $negative = $this->ledger->issue($this->variant, $this->warehouse, '1', allowNegative: true, lot: $lot, requireLot: true);
        $this->assertSame('-1.0000', $this->stock()->balanceOf($lot));
        $this->assertCount(1, $negative);
    }

    private function lotWithStock(string $lotNo, ?string $bestBefore, string $qty, ?WarehouseBin $bin = null): StockLot {
        $lot = StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $this->variant->id, 'lot_no' => $lotNo, 'best_before' => $bestBefore]);
        $this->ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, $qty, StockMovementType::Receipt, stockLotId: $lot->id, bin: $bin));

        return $lot;
    }

    private function stock(): LotStockReader {
        return app(LotStockReader::class);
    }

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
