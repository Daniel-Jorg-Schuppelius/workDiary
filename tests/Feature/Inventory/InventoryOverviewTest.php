<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{ReservationStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryLedger, LotService, LotStockReader, ReservationService, StockLevelService, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bestandsübersicht-UI (Feature 048, P4): Bewertung/Reservierungen/Meldebestand
 * sichtbar; Reservierungsfreigabe (inventory.post) und Mindest-/Meldebestand-
 * Pflege (inventory.configure); gesperrter Bestand und Entnahme je Charge (E7).
 */
final class InventoryOverviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private User $teamlead; // post, NICHT configure
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = $this->makeVariant();
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '10');
    }

    public function test_overview_renders_with_below_reorder(): void {
        app(StockLevelService::class)->setLevels($this->variant, $this->warehouse, '3', '20'); // verfügbar 10 < 20

        $this->actingAs($this->admin)
            ->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))
            ->assertOk()
            ->assertSee(__('inventory.overview.below_reorder'));
    }

    public function test_release_reservation_restores_availability(): void {
        $reservation = app(ReservationService::class)->reserve($this->variant, $this->warehouse, '4');
        $this->assertSame('6.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        $this->actingAs($this->teamlead)
            ->post(route('inventory.reservations.release', $reservation))
            ->assertRedirect();

        $this->assertSame(ReservationStatus::Released, $reservation->fresh()->status);
        $this->assertSame('10.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    public function test_release_forbidden_without_post_permission(): void {
        $reservation = app(ReservationService::class)->reserve($this->variant, $this->warehouse, '2');
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('inventory.reservations.release', $reservation))->assertForbidden();
    }

    public function test_set_levels_requires_configure(): void {
        $payload = [
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'min_stock' => '5',
            'reorder_point' => '12',
        ];

        $this->actingAs($this->teamlead)->post(route('inventory.levels.set'), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('inventory.levels.set'), $payload)->assertRedirect();

        $this->assertDatabaseHas('stock_level_settings', [
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    /** Die Bestandstabelle blättert über die Varianten; Saldo und Bewertung gehören auf jeder Seite zur Zeile. */
    public function test_stock_table_pages_with_the_balance_of_each_variant(): void {
        $ledger = app(InventoryLedger::class);
        $last = $this->variant;
        foreach (range(1, 51) as $i) {
            $last = $this->makeVariant();
            $ledger->receipt($last, $this->warehouse, (string) $i);
        }

        $first = $this->actingAs($this->admin)->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))->assertOk();
        $rows = $first->viewData('rows');
        $this->assertSame(52, $rows->total());
        $this->assertCount(50, $rows->items());
        $this->assertSame($this->variant->id, $rows->items()[0]['variant']->id);
        $this->assertSame('10.0000', $rows->items()[0]['available']);
        $first->assertSee('warehouse=' . $this->warehouse->sqid . '&amp;page=2', false);

        $second = $this->actingAs($this->admin)->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid, 'page' => 2]))->assertOk();
        $tail = $second->viewData('rows')->items();
        $this->assertCount(2, $tail);
        $this->assertSame($last->id, $tail[1]['variant']->id);
        $this->assertSame('51.0000', $tail[1]['available']);
        $this->assertSame('51.0000', $tail[1]['physical']);
    }

    /** Gesperrter Bestand erscheint als eigene Spalte, nur wenn es ihn gibt; „verfügbar“ ist darum gemindert. */
    public function test_blocked_stock_shows_next_to_reserved_only_when_not_zero(): void {
        $page = fn () => $this->actingAs($this->admin)->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))->assertOk();
        $lots = app(LotService::class);
        $lot = $lots->register($this->variant, 'L-SPERRE');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '4', '2', $lot);

        $before = $page();
        $this->assertFalse($before->viewData('showBlocked'));
        $this->assertNull($before->viewData('rows')->items()[0]['blocked']);

        $lots->block($lot, 'Rückruf', $this->admin);

        $after = $page();
        $this->assertTrue($after->viewData('showBlocked'));
        $row = $after->viewData('rows')->items()[0];
        $this->assertSame(['4.0000', '10.0000', '14.0000'], [$row['blocked'], $row['available'], $row['physical']]);
        $after->assertSeeInOrder([__('inventory.field.reserved'), __('inventory.state.blocked'), __('inventory.overview.avg')]);
    }

    /** Die Chargenauswahl listet aktive Chargen mit Bestand in diesem Lager, je Option mit ihrer Variante. */
    public function test_lot_field_lists_the_issuable_lots_of_the_warehouse(): void {
        $lots = app(LotService::class);
        $active = $lots->register($this->variant, 'L-FREI', '2026-12-01');
        $held = $lots->register($this->variant, 'L-GESPERRT');
        $empty = $lots->register($this->variant, 'L-LEER');
        $elsewhere = $lots->register($this->variant, 'L-ANDERSWO');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $active);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '2', '2', $held);
        $lots->receiveIntoLot($this->variant, Warehouse::factory()->create(['organization_id' => $this->organization->id]), '2', '2', $elsewhere);
        $lots->block($held, 'Rückruf', $this->admin);

        $response = $this->actingAs($this->admin)->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))->assertOk();

        $this->assertSame(['L-FREI'], array_map(fn (array $option): string => $option[0]->lot_no, $response->viewData('lotOptions')));
        $response->assertSee('name="lot"', false)
            ->assertSee('value="' . $active->sqid . '" data-parent="' . $this->variant->sqid . '"', false)
            ->assertSee('data-depends-on="variant"', false)
            ->assertDontSee('L-GESPERRT')
            ->assertDontSee($empty->sqid, false)
            ->assertDontSee('L-ANDERSWO');

        // Ohne Chargenbestand im Lager gibt es das Feld nicht.
        $other = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin)->get(route('inventory.stock', ['warehouse' => $other->sqid]))->assertOk()->assertDontSee('name="lot"', false);
    }

    /** Manuelle Entnahme mit gewählter Charge bucht genau sie; leer bleibt FEFO, Reservierungen bleiben chargenlos. */
    public function test_manual_issue_books_the_chosen_lot(): void {
        $lots = app(LotService::class);
        $early = $lots->register($this->variant, 'L-EARLY', '2026-05-01');
        $late = $lots->register($this->variant, 'L-LATE', '2026-09-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $early);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $late);

        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '2', ['lot' => $late->sqid]))
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '1'))
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('reserve', '1', ['lot' => $late->sqid]))
            ->assertSessionHas('success');

        $stock = app(LotStockReader::class);
        $this->assertSame(['2.0000', '1.0000'], [$stock->balanceOf($early), $stock->balanceOf($late)]);
        $this->assertNull(StockMovement::query()->where('movement_type', StockMovementType::Reserve->value)->sole()->stock_lot_id);
    }

    /** Eine Charge fremder Variante oder ein unbekannter Wert wird abgewiesen, ohne zu buchen. */
    public function test_manual_issue_rejects_a_foreign_or_unknown_lot(): void {
        $other = $this->makeVariant();
        $foreign = app(LotService::class)->register($other, 'L-FREMD');
        app(LotService::class)->receiveIntoLot($other, $this->warehouse, '3', '2', $foreign);
        $issues = fn (): int => StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->count();

        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '1', ['lot' => $foreign->sqid]))
            ->assertSessionHas('error', __('inventory.lot.error.foreign', ['lot' => 'L-FREMD']));
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '1', ['lot' => 'kein-sqid']))
            ->assertSessionHas('error', __('inventory.lot.flash.unknown'));

        $this->assertSame(0, $issues());
    }

    /**
     * Chargenpflicht (M19): Zugang nur über den Wareneingang; die Entnahme ist erlaubt, wenn die Chargen die
     * Menge decken — gewählt oder FEFO, auch mit Materialkosten auf einen Kunden. Serienpflicht bleibt gesperrt.
     */
    public function test_batch_required_article_issues_only_from_lots(): void {
        $tracked = $this->makeVariant(['batch_required' => true]);
        $lot = app(LotService::class)->register($tracked, 'L-PFLICHT');
        app(LotService::class)->receiveIntoLot($tracked, $this->warehouse, '3', '2', $lot);
        app(InventoryLedger::class)->post(new StockPosting($tracked, $this->warehouse, StockState::Physical, '5.0000', StockMovementType::Receipt));
        $issues = fn (): int => StockMovement::query()->where('article_variant_id', $tracked->id)->where('movement_type', StockMovementType::Issue->value)->count();
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->admin->id, 'currency' => 'EUR']);

        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('receipt', '1', variant: $tracked))
            ->assertSessionHas('error', __('inventory.error.tracked_article_manual_move'));
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '4', variant: $tracked))
            ->assertSessionHas('error', __('inventory.error.lot_required'));
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '4', ['cost_customer' => $customer->sqid], $tracked))
            ->assertSessionHas('error', __('inventory.error.lot_required'));
        $this->assertSame(0, $issues());

        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '2', variant: $tracked))
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '1', ['lot' => $lot->sqid, 'cost_customer' => $customer->sqid], $tracked))
            ->assertSessionHas('success');
        $this->assertSame(2, $issues());
        $this->assertSame('0.0000', app(LotStockReader::class)->balanceOf($lot));
        $this->assertSame(1, $customer->materialCostAllocations()->count());

        $serial = $this->makeVariant(['serial_required' => true]);
        app(InventoryLedger::class)->receipt($serial, $this->warehouse, '2');
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), $this->movement('issue', '1', variant: $serial))
            ->assertSessionHas('error', __('inventory.error.tracked_article_manual_move'));
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function movement(string $movement, string $qty, array $extra = [], ?ArticleVariant $variant = null): array {
        return [
            'warehouse' => $this->warehouse->sqid,
            'variant' => ($variant ?? $this->variant)->sqid,
            'movement' => $movement,
            'qty' => $qty,
            'ownership' => 'own',
            ...$extra,
        ];
    }

    /** @param array<string, mixed> $article */
    private function makeVariant(array $article = []): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, ...$article]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
