<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockLotStatus, StockMovementType, StockState};
use App\Enums\User\Permission as P;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryLedger, LotService, LotStockReader, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Chargen-UI (Feature 047/048, E2/E7): Liste, Los-Split und Sperre über HTTP;
 * die Sperre ist eine Buchung in den Zustand „gesperrt“ (E5).
 */
final class LotUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_index_renders(): void {
        $lot = app(LotService::class)->register($this->variant, 'L1');
        app(LotService::class)->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $lot);

        $this->actingAs($this->admin)->get(route('inventory.lots'))->assertOk()->assertSee('L1');
    }

    /** Die Liste zeigt den Chargenstand als Label — auch für gesperrte und zusammengeführte Chargen. */
    public function test_index_shows_the_lot_status_label(): void {
        $lots = app(LotService::class);
        $blocked = $lots->block($lots->register($this->variant, 'L-BLOCK'), 'Reklamation', $this->admin);
        $merged = $lots->register($this->variant, 'L-MERGED');
        app(\App\Services\Inventory\LotSplitService::class)->merge($merged, $lots->register($this->variant, 'L-ZIEL'));

        $this->assertSame(StockLotStatus::Blocked, $blocked->fresh()?->status);
        $this->assertSame(StockLotStatus::Merged, $merged->fresh()?->status);

        $this->actingAs($this->admin)->get(route('inventory.lots'))
            ->assertOk()
            ->assertSeeInOrder(['L-ZIEL', StockLotStatus::Active->label(), 'L-MERGED', StockLotStatus::Merged->label(), 'L-BLOCK', StockLotStatus::Blocked->label()]);
    }

    public function test_block_and_release_need_a_reason_and_are_audited(): void {
        $lots = app(LotService::class);
        $lot = $lots->register($this->variant, 'L-QS');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $lot);

        $this->actingAs($this->admin)->get(route('inventory.lots'))
            ->assertOk()
            ->assertSee(route('inventory.lots.block.create', $lot), false)
            ->assertDontSee(route('inventory.lots.unblock.create', $lot), false);
        $this->actingAs($this->admin)->get(route('inventory.lots.block.create', $lot))
            ->assertOk()
            ->assertSee(route('inventory.lots.block.store', $lot), false)
            ->assertSee('name="reason"', false);

        // Ohne Begründung keine Sperre.
        $this->actingAs($this->admin)->post(route('inventory.lots.block.store', $lot), ['reason' => '  '])
            ->assertSessionHasErrors('reason');
        $this->assertSame(StockLotStatus::Active, $lot->fresh()?->status);

        $this->actingAs($this->admin)->post(route('inventory.lots.block.store', $lot), ['reason' => 'Reklamation des Lieferanten'])
            ->assertRedirect(route('inventory.lots'))
            ->assertSessionHas('success', __('inventory.lot.flash.blocked', ['lot' => 'L-QS']));

        $lot->refresh();
        $this->assertSame(StockLotStatus::Blocked, $lot->status);
        $this->assertSame('Reklamation des Lieferanten', $lot->blocked_reason);
        $this->assertSame($this->admin->id, $lot->blocked_by_user_id);
        $this->assertNotNull($lot->blocked_at);
        $this->assertSame('10.0000', app(LotStockReader::class)->balanceOf($lot));
        $log = AuditLog::query()->where('event', 'stock_lot.blocked')->sole();
        $this->assertSame([$this->admin->id, $lot->id, 'Reklamation des Lieferanten'], [$log->user_id, $log->auditable_id, $log->changes['reason']]);
        $this->assertSame(__('audit-events.stock_lot.blocked'), $log->eventLabel());

        // Die Liste nennt Grund und Person und bietet nur noch die Freigabe an.
        $this->actingAs($this->admin)->get(route('inventory.lots'))
            ->assertOk()
            ->assertSee('Reklamation des Lieferanten')
            ->assertSee($this->admin->name)
            ->assertSee(route('inventory.lots.unblock.create', $lot), false)
            ->assertDontSee(route('inventory.lots.block.create', $lot), false);
        $this->actingAs($this->admin)->get(route('inventory.lots.block.create', $lot))->assertNotFound();
        $this->actingAs($this->admin)->get(route('inventory.lots.unblock.create', $lot))
            ->assertOk()
            ->assertSee(route('inventory.lots.unblock.store', $lot), false)
            ->assertSee('Reklamation des Lieferanten');

        $this->actingAs($this->admin)->post(route('inventory.lots.unblock.store', $lot), [])
            ->assertSessionHasErrors('reason');
        $this->assertSame(StockLotStatus::Blocked, $lot->fresh()?->status);

        $this->actingAs($this->admin)->post(route('inventory.lots.unblock.store', $lot), ['reason' => 'Prüfung ohne Befund'])
            ->assertRedirect(route('inventory.lots'))
            ->assertSessionHas('success', __('inventory.lot.flash.unblocked', ['lot' => 'L-QS']));

        $lot->refresh();
        $this->assertSame(StockLotStatus::Active, $lot->status);
        $this->assertNull($lot->blocked_reason);
        $this->assertNull($lot->blocked_by_user_id);
        $this->assertNull($lot->blocked_at);
        $this->assertSame('Prüfung ohne Befund', AuditLog::query()->where('event', 'stock_lot.released')->sole()->changes['reason']);
    }

    public function test_block_and_release_need_the_posting_right(): void {
        $lots = app(LotService::class);
        $active = $lots->register($this->variant, 'L-AKTIV');
        $blocked = $lots->block($lots->register($this->variant, 'L-GESPERRT'), 'Reklamation', $this->admin);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(P::InventoryViewAny->value);

        // Lesen ja, aber weder Knopf noch Dialog noch Aktion.
        $this->actingAs($viewer)->get(route('inventory.lots'))
            ->assertOk()
            ->assertSee('L-AKTIV')
            ->assertDontSee(route('inventory.lots.block.create', $active), false)
            ->assertDontSee(route('inventory.lots.unblock.create', $blocked), false);
        $this->actingAs($viewer)->get(route('inventory.lots.block.create', $active))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.lots.block.store', $active), ['reason' => 'Versuch'])->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory.lots.unblock.create', $blocked))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.lots.unblock.store', $blocked), ['reason' => 'Versuch'])->assertForbidden();

        $this->assertSame(StockLotStatus::Active, $active->fresh()?->status);
        $this->assertSame(StockLotStatus::Blocked, $blocked->fresh()?->status);
        $this->assertSame(1, AuditLog::query()->where('event', 'like', 'stock_lot.%')->count());

        // Gegenprobe: mit dem Buchungsrecht geht dieselbe Anfrage durch.
        $viewer->givePermissionTo(P::InventoryPost->value);
        $this->actingAs($viewer)->post(route('inventory.lots.block.store', $active), ['reason' => 'Versuch'])->assertRedirect(route('inventory.lots'));
        $this->assertSame(StockLotStatus::Blocked, $active->fresh()?->status);
    }

    public function test_merged_lot_can_neither_be_blocked_nor_released(): void {
        $lots = app(LotService::class);
        $merged = $lots->register($this->variant, 'L-MERGED');
        $target = $lots->register($this->variant, 'L-ZIEL');
        app(\App\Services\Inventory\LotSplitService::class)->merge($merged, $target);

        $this->actingAs($this->admin)->get(route('inventory.lots'))
            ->assertOk()
            ->assertSee(__('inventory.lot.merged_into', ['lot' => 'L-ZIEL']))
            ->assertDontSee(route('inventory.lots.block.create', $merged), false)
            ->assertDontSee(route('inventory.lots.unblock.create', $merged), false);
        $this->actingAs($this->admin)->get(route('inventory.lots.block.create', $merged))->assertNotFound();
        $this->actingAs($this->admin)->get(route('inventory.lots.unblock.create', $merged))->assertNotFound();

        foreach (['inventory.lots.block.store', 'inventory.lots.unblock.store'] as $action) {
            $this->actingAs($this->admin)->post(route($action, $merged), ['reason' => 'Versuch'])
                ->assertRedirect(route('inventory.lots'))
                ->assertSessionHas('error');
        }
        $this->assertSame(StockLotStatus::Merged, $merged->fresh()?->status);
        $this->assertNull($merged->fresh()?->blocked_reason);

        // Eine aktive Charge lässt sich nicht „freigeben", eine gesperrte nicht noch einmal sperren.
        $this->actingAs($this->admin)->post(route('inventory.lots.unblock.store', $target), ['reason' => 'Versuch'])->assertSessionHas('error');
        $lots->block($target, 'Reklamation', $this->admin);
        $this->actingAs($this->admin)->post(route('inventory.lots.block.store', $target), ['reason' => 'Noch einmal'])->assertSessionHas('error');
        $this->assertSame('Reklamation', $target->fresh()?->blocked_reason);
    }

    public function test_merge_action_moves_stock_and_rejects_a_blocked_lot(): void {
        $lots = app(LotService::class);
        $a = $lots->register($this->variant, 'L-A');
        $b = $lots->register($this->variant, 'L-B');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '6', '2', $a);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);
        $lots->block($b, 'Reklamation', $this->admin);

        $this->actingAs($this->admin)->post(route('inventory.lots.merge'), ['from' => $b->sqid, 'into' => $a->sqid])
            ->assertRedirect(route('inventory.lots'))
            ->assertSessionHas('error');
        $this->assertSame(StockLotStatus::Blocked, $b->fresh()?->status);

        $lots->unblock($b, 'Prüfung ohne Befund', $this->admin);
        $this->actingAs($this->admin)->post(route('inventory.lots.merge'), ['from' => $b->sqid, 'into' => $a->sqid])
            ->assertRedirect(route('inventory.lots'))
            ->assertSessionHas('success');

        $this->assertSame(StockLotStatus::Merged, $b->fresh()?->status);
        $this->assertSame('11.0000', app(LotStockReader::class)->balanceOf($a));
        $this->assertDatabaseHas('stock_movements', ['stock_lot_id' => $a->id, 'movement_type' => 'transfer_in', 'actor_user_id' => $this->admin->id]);
    }

    public function test_split_action(): void {
        $lot = app(LotService::class)->register($this->variant, 'L1');
        app(LotService::class)->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $lot);

        $this->actingAs($this->admin)->post(route('inventory.lots.split'), [
            'lot' => $lot->sqid, 'qty' => '4', 'new_lot_no' => 'L1-A',
        ])->assertRedirect();

        $split = StockLot::query()->where('lot_no', 'L1-A')->sole();
        $this->assertSame(['6.0000', '4.0000'], [app(LotStockReader::class)->balanceOf($lot), app(LotStockReader::class)->balanceOf($split)]);
        $this->assertDatabaseHas('stock_movements', ['stock_lot_id' => $split->id, 'movement_type' => 'transfer_in', 'actor_user_id' => $this->admin->id]);
    }

    /** Sperren bucht den physischen Saldo je Topf in „gesperrt“, Freigeben bucht ihn zurück; die Ware bleibt liegen. */
    public function test_block_books_the_lot_balance_per_pot_and_release_books_it_back(): void {
        $ledger = app(InventoryLedger::class);
        $lots = app(LotService::class);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R-01']);
        $lot = $lots->register($this->variant, 'L-SPERRE');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '4', '2', $lot);
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '6.0000', StockMovementType::Receipt, stockLotId: $lot->id, bin: $bin));
        $ledger->receipt($this->variant, $this->warehouse, '5');
        $this->assertSame('15.0000', $ledger->available($this->variant, $this->warehouse));

        $this->actingAs($this->admin)->post(route('inventory.lots.block.store', $lot), ['reason' => 'Rückruf'])
            ->assertRedirect(route('inventory.lots'));

        $holds = StockMovement::query()->where('movement_type', StockMovementType::LotBlock->value)->orderBy('id')->get();
        $this->assertSame(
            [[null, '4.0000', 'lot-block:' . $lot->id . ':1'], [$bin->id, '6.0000', 'lot-block:' . $lot->id . ':2']],
            $holds->map(fn (StockMovement $m): array => [$m->bin_id, $m->qty_base, $m->idempotency_key])->all(),
        );
        foreach ($holds as $hold) {
            $this->assertSame(
                [StockState::Blocked, $lot->id, $this->admin->id, $lot->getMorphClass(), $lot->id],
                [$hold->stock_state, $hold->stock_lot_id, $hold->actor_user_id, $hold->source_type, $hold->source_id],
            );
        }
        // Die Ware bleibt liegen, verfügbar ist nur noch der Bestand ohne Charge.
        $this->assertSame('15.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('5.0000', $ledger->available($this->variant, $this->warehouse));
        $this->assertSame('0.0000', $ledger->availableInBin($this->variant, $this->warehouse, $bin));
        $this->assertSame('10.0000', app(LotStockReader::class)->balanceOf($lot));

        $this->actingAs($this->admin)->post(route('inventory.lots.unblock.store', $lot), ['reason' => 'Prüfung ohne Befund'])
            ->assertRedirect(route('inventory.lots'));

        $releases = StockMovement::query()->where('movement_type', StockMovementType::LotRelease->value)->orderBy('id')->get();
        $this->assertSame(
            [[null, '-4.0000', 'lot-release:' . $lot->id . ':1'], [$bin->id, '-6.0000', 'lot-release:' . $lot->id . ':2']],
            $releases->map(fn (StockMovement $m): array => [$m->bin_id, $m->qty_base, $m->idempotency_key])->all(),
        );
        $this->assertSame('0.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Blocked));
        $this->assertSame('15.0000', $ledger->available($this->variant, $this->warehouse));

        // Erneut gesperrt: die laufende Nummer setzt fort, die Schlüssel der ersten Sperre bleiben unberührt.
        $lots->block($lot->fresh() ?? $lot, 'Zweite Prüfung', $this->admin);
        $this->assertSame(
            ['lot-block:' . $lot->id . ':1', 'lot-block:' . $lot->id . ':2', 'lot-block:' . $lot->id . ':3', 'lot-block:' . $lot->id . ':4'],
            StockMovement::query()->where('movement_type', StockMovementType::LotBlock->value)->orderBy('id')->pluck('idempotency_key')->all(),
        );
        $this->assertSame('5.0000', $ledger->available($this->variant, $this->warehouse));
    }

    /** Eine gesperrte Charge ist geschlossen: der Wareneingang in sie wird abgewiesen. */
    public function test_receipt_into_a_blocked_lot_is_rejected(): void {
        $lots = app(LotService::class);
        $lot = $lots->block($lots->register($this->variant, 'L-ZU'), 'Reklamation', $this->admin);

        try {
            $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $lot);
            $this->fail('Zugang an eine gesperrte Charge muss scheitern.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.lot.error.receipt_into_blocked', ['lot' => 'L-ZU']), $e->getMessage());
        }
        $this->assertSame(0, StockMovement::query()->count());
    }

    /** Altbestand: gesperrte Chargen bekommen ihre Sperrbuchung nachgetragen — einmal, aktive keine. */
    public function test_migration_posts_the_balance_of_blocked_lots_once(): void {
        $ledger = app(InventoryLedger::class);
        $lots = app(LotService::class);
        $held = $lots->register($this->variant, 'L-ALT');
        $legacy = $lots->register($this->variant, 'L-ALT-QUELLE');
        $active = $lots->register($this->variant, 'L-AKTIV');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '5', '2', $held);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '2', '2', $legacy);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $active);
        // Stand vor der Sperrbuchung: gesperrt war nur der Status, zusammengeführt ohne Umbuchung.
        DB::table('stock_lots')->where('id', $held->id)->update(['status' => StockLotStatus::Blocked->value]);
        DB::table('stock_lots')->where('id', $legacy->id)->update(['status' => StockLotStatus::Merged->value, 'merged_into_lot_id' => $held->id]);
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));

        $migration = require database_path('migrations/2027_03_10_100900_post_lot_block_balances.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            [[$held->id, StockState::Blocked, '7.0000', 'lot-block:' . $held->id . ':1', null]],
            StockMovement::query()->where('movement_type', StockMovementType::LotBlock->value)->get()
                ->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->stock_state, $m->qty_base, $m->idempotency_key, $m->actor_user_id])->all(),
        );
        $this->assertSame('3.0000', $ledger->available($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical));

        // Die nachgetragene Sperre gibt die Freigabe wie jede andere zurück.
        $lots->unblock($held, 'Prüfung ohne Befund', $this->admin);
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));
        $this->assertSame('3.0000', app(LotStockReader::class)->balanceOf($active));
    }

    /**
     * Etikettendruck je Charge (Vollscan 2026-09-15, `C1-05` / `MVP-798`):
     * Die Route gab es, aber weder Verlinkung in der Chargenliste noch Test.
     */
    public function test_label_pdf_renders_for_a_lot(): void {
        $lot = app(LotService::class)->register($this->variant, 'L-ETIKETT');

        $this->actingAs($this->admin)
            ->get(route('inventory.labels.lot', $lot))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
