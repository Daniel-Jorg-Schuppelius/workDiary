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

use App\Enums\Inventory\ReservationStatus;
use App\Models\{Article, ArticleVariant, User, Warehouse};
use App\Services\Inventory\{InventoryLedger, ReservationService, StockLevelService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bestandsübersicht-UI (Feature 048, P4): Bewertung/Reservierungen/Meldebestand
 * sichtbar; Reservierungsfreigabe (inventory.post) und Mindest-/Meldebestand-
 * Pflege (inventory.configure).
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

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
