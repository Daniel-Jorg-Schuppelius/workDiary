<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryPermissionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{ReservationStatus, StockCountStatus};
use App\Enums\User\Permission as P;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryLedger, ReservationService, StocktakeService, ValuationService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1585: getrennte Lagerrechte, Vier-Augen-Freigabe der Inventurdifferenzen
 * und änderbare Reservierungen.
 */
final class InventoryPermissionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default', 'sku' => 'SKU-R',
        ]);
    }

    private function userWith(P ...$permissions): User {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }

        return $user;
    }

    public function test_counting_and_approving_are_separate_rights(): void {
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '10');
        $counter = $this->userWith(P::InventoryViewAny, P::InventoryCount);
        $poster = $this->userWith(P::InventoryViewAny, P::InventoryPost);

        // Buchen allein reicht nicht mehr zum Zählen.
        $this->actingAs($poster)->post(route('inventory.counts.open'), ['warehouse' => $this->warehouse->sqid])->assertForbidden();
        $this->actingAs($counter)->post(route('inventory.counts.open'), ['warehouse' => $this->warehouse->sqid])->assertRedirect();
        $count = \App\Models\Inventory\StockCount::query()->sole();
        $line = $count->lines()->sole();

        $this->actingAs($counter)->post(route('inventory.counts.record', $count), ['counted' => [$line->id => '8']])->assertSessionHas('success');
        // Zählen heißt nicht freigeben, und Zählen heißt nicht buchen.
        $this->actingAs($counter)->post(route('inventory.counts.apply', $count))->assertForbidden();
        $this->actingAs($counter)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'receipt', 'qty' => '1', 'ownership' => 'own',
        ])->assertForbidden();

        $approver = $this->userWith(P::InventoryViewAny, P::InventoryCountApprove);
        $this->actingAs($approver)->post(route('inventory.counts.apply', $count))->assertRedirect();
        $this->assertSame(StockCountStatus::Completed, $count->fresh()->status);
        $this->assertSame('8.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    public function test_four_eyes_blocks_the_counter_above_the_threshold_and_audits_the_approval(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '5.00');
        $this->organization->update(['settings' => ['inventory' => ['four_eyes' => ['count' => true, 'count_threshold' => 8]]]]);
        $both = $this->userWith(P::InventoryViewAny, P::InventoryCount, P::InventoryCountApprove);
        $stocktake = app(StocktakeService::class);

        $count = $stocktake->open($this->warehouse, $both->id);
        $stocktake->recordCount($count->lines()->sole(), '8', $both->id); // Differenz 2 × 5,00 = 10,00 ≥ 8,00

        $this->actingAs($both)->post(route('inventory.counts.apply', $count))
            ->assertSessionHas('error', __('inventory.count_ui.four_eyes_blocked'));
        $this->assertSame(StockCountStatus::Counting, $count->fresh()->status);
        $this->assertSame('10.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        $second = $this->userWith(P::InventoryViewAny, P::InventoryCountApprove);
        $this->actingAs($second)->post(route('inventory.counts.apply', $count))->assertSessionMissing('error');
        $this->assertSame(StockCountStatus::Completed, $count->fresh()->status);

        $audit = AuditLog::query()->where('event', 'inventory.countApproved')
            ->where('auditable_type', MorphMap::stableKey(\App\Models\Inventory\StockCount::class))->sole();
        $this->assertSame($second->id, $audit->user_id);
        $this->assertTrue($audit->changes['four_eyes']);
        $this->assertSame([$both->id], $audit->changes['counted_by']);
        $this->assertSame(1, $audit->changes['flagged_lines']);

        // Unter dem Grenzwert darf dieselbe Person freigeben.
        $small = $stocktake->open($this->warehouse, $both->id);
        $stocktake->recordCount($small->lines()->sole(), '7', $both->id); // Differenz 1 × 5,00 < 8,00
        $this->actingAs($both)->post(route('inventory.counts.apply', $small))->assertSessionMissing('error');
        $this->assertSame(StockCountStatus::Completed, $small->fresh()->status);
    }

    public function test_reservations_need_their_own_right_and_can_be_changed(): void {
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '10');
        $reservation = app(ReservationService::class)->reserve($this->variant, $this->warehouse, '6');
        $poster = $this->userWith(P::InventoryViewAny, P::InventoryPost);
        $reserver = $this->userWith(P::InventoryViewAny, P::InventoryReserve);

        $this->actingAs($poster)->get(route('inventory.reservations.edit', $reservation))->assertForbidden();
        $this->actingAs($poster)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'reserve', 'qty' => '1', 'ownership' => 'own',
        ])->assertForbidden();

        $this->actingAs($reserver)->get(route('inventory.reservations.edit', $reservation))->assertOk()->assertSee(__('inventory.reservation.open'));
        $this->actingAs($reserver)->put(route('inventory.reservations.update', $reservation), ['priority' => '10', 'release_qty' => '2.5'])
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame(10, $reservation->priority);
        $this->assertSame('3.5000', $reservation->openQuantity());
        $this->assertSame(ReservationStatus::Active, $reservation->status);
        $this->assertSame('6.5000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        $this->actingAs($reserver)->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))->assertOk()->assertSee(__('inventory.movement.reserve'));
    }

    public function test_reservation_can_be_redistributed_to_a_higher_priority_need(): void {
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '10');
        $service = app(ReservationService::class);
        $low = $service->reserve($this->variant, $this->warehouse, '6', priority: 200);
        $high = $service->reserve($this->variant, $this->warehouse, '4', priority: 10);
        $reserver = $this->userWith(P::InventoryViewAny, P::InventoryReserve);

        // Keine stille Verdrängung: die Umverteilung ist eine eigene, sichtbare Aktion.
        $this->actingAs($reserver)->get(route('inventory.reservations.edit', $low))->assertOk()
            ->assertSee('name="redistribute_to"', false)->assertSee($high->sqid);
        $this->actingAs($reserver)->put(route('inventory.reservations.update', $low), ['priority' => '200', 'redistribute_to' => $high->sqid, 'redistribute_qty' => '7'])
            ->assertSessionHasErrors('redistribute_qty');
        $this->actingAs($reserver)->put(route('inventory.reservations.update', $low), ['priority' => '200', 'redistribute_to' => $high->sqid, 'redistribute_qty' => '2.5'])
            ->assertSessionHas('success');

        $this->assertSame('3.5000', $low->fresh()->openQuantity());
        $this->assertSame('6.5000', $high->fresh()->openQuantity());
        $this->assertSame('0.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse), 'Der reservierte Bestand bleibt gleich.');
        $audit = AuditLog::query()->where('event', 'inventory.reservationRedistributed')->sole();
        $this->assertSame((int) $low->id, (int) $audit->changes['from_reservation_id']);

        // Die ganze offene Menge (leer) schließt die abgebende Reservierung.
        $this->actingAs($reserver)->put(route('inventory.reservations.update', $low), ['priority' => '200', 'redistribute_to' => $high->sqid])->assertSessionHas('success');
        $this->assertSame(ReservationStatus::Released, $low->fresh()->status);
        $this->assertSame('10.0000', $high->fresh()->openQuantity());
    }

    public function test_migration_grants_the_split_rights_to_holders_of_the_old_ones(): void {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'lager-eigen', 'guard_name' => 'web', 'team_id' => $this->organization->id]);
        Permission::findOrCreate(P::InventoryPost->value, 'web');
        $role->givePermissionTo(P::InventoryPost->value);
        Permission::query()->whereIn('name', ['inventory.count', 'inventory.reserve', 'inventory.externalResolve', 'inventory.countApprove'])->delete();

        (require database_path('migrations/2027_04_17_180100_grant_split_inventory_permissions.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = $role->fresh()->permissions->pluck('name')->all();
        $this->assertContains('inventory.count', $names);
        $this->assertContains('inventory.reserve', $names);
        $this->assertContains('inventory.externalResolve', $names);
        $this->assertNotContains('inventory.countApprove', $names);
    }
}
