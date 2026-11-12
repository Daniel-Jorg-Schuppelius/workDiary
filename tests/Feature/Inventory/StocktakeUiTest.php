<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StocktakeUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockCountStatus, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Services\Inventory\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Inventur-UI (Feature 048, MVP-069): Eröffnen/Zählen mit inventory.post,
 * Differenzfreigabe nur mit inventory.configure.
 */
final class StocktakeUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private User $teamlead; // post, aber NICHT configure
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

    public function test_index_renders(): void {
        $this->actingAs($this->admin)
            ->get(route('inventory.counts.index', ['warehouse' => $this->warehouse->sqid]))
            ->assertOk();
    }

    public function test_open_requires_post_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('inventory.counts.open'), ['warehouse' => $this->warehouse->sqid])->assertForbidden();
        $this->actingAs($this->teamlead)->post(route('inventory.counts.open'), ['warehouse' => $this->warehouse->sqid])->assertRedirect();

        $this->assertDatabaseHas('stock_counts', ['warehouse_id' => $this->warehouse->id]);
    }

    public function test_full_count_flow(): void {
        $count = app(\App\Services\Inventory\StocktakeService::class)->open($this->warehouse);
        $line = $count->lines->firstWhere('stock_state', StockState::Physical);

        $this->actingAs($this->admin)->get(route('inventory.counts.show', $count))->assertOk();

        // Zählen (Teamleitung darf)
        $this->actingAs($this->teamlead)
            ->post(route('inventory.counts.record', $count), ['counted' => [$line->id => '8']])
            ->assertRedirect();
        $this->assertSame('8.0000', $line->fresh()->counted_qty);

        // Differenzfreigabe braucht configure: Teamleitung NICHT, Admin ja
        $this->actingAs($this->teamlead)->post(route('inventory.counts.apply', $count))->assertForbidden();
        $this->actingAs($this->admin)->post(route('inventory.counts.apply', $count))->assertRedirect();

        $this->assertSame(StockCountStatus::Completed, $count->fresh()->status);
        $this->assertSame('8.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    /** MVP-1620: Zählung abschließen friert die Mengen zur Prüfung ein; Verwerfen bucht nichts. */
    public function test_review_step_and_cancellation(): void {
        $count = app(\App\Services\Inventory\StocktakeService::class)->open($this->warehouse);
        $line = $count->lines->firstWhere('stock_state', StockState::Physical);
        $review = ['status' => StockCountStatus::Review->value];

        $this->actingAs($this->teamlead)->post(route('inventory.counts.transition', $count), $review)->assertSessionHas('error');
        $this->assertSame(StockCountStatus::Counting, $count->fresh()?->status, 'Ohne Zählmenge keine Prüfung.');

        $this->actingAs($this->teamlead)->post(route('inventory.counts.record', $count), ['counted' => [$line->id => '9']]);
        $this->actingAs($this->teamlead)->post(route('inventory.counts.transition', $count), $review)->assertSessionHas('success');
        $this->assertSame(StockCountStatus::Review, $count->fresh()?->status);
        $this->actingAs($this->teamlead)->post(route('inventory.counts.record', $count), ['counted' => [$line->id => '1']])->assertStatus(422);
        $this->actingAs($this->admin)->get(route('inventory.counts.show', $count))->assertOk()
            ->assertSee(__('inventory.count_ui.reopen'))->assertDontSee(__('inventory.count_ui.submit_review'));

        // Wieder öffnen und Verwerfen gehören zur Freigabe.
        $this->actingAs($this->teamlead)->post(route('inventory.counts.transition', $count), ['status' => StockCountStatus::Counting->value])->assertForbidden();
        $this->actingAs($this->admin)->post(route('inventory.counts.transition', $count), ['status' => StockCountStatus::Counting->value])->assertSessionHas('success');
        $this->assertSame(StockCountStatus::Counting, $count->fresh()?->status);
        $this->actingAs($this->admin)->post(route('inventory.counts.transition', $count), ['status' => StockCountStatus::Completed->value])->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->post(route('inventory.counts.transition', $count), ['status' => StockCountStatus::Cancelled->value])->assertSessionHas('success');
        $this->assertSame(StockCountStatus::Cancelled, $count->fresh()?->status);
        $this->assertSame('10.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical), 'Verworfene Inventur bucht nichts.');
        $this->actingAs($this->admin)->post(route('inventory.counts.apply', $count))->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.countStatusChanged', 'auditable_id' => $count->id]);
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
