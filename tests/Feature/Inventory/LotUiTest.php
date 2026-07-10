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

use App\Models\{Article, ArticleVariant, StockLot, User, Warehouse};
use App\Services\Inventory\LotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Chargen-UI (Feature 047/048, E2/E7): Liste und Los-Split über HTTP.
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

    public function test_split_action(): void {
        $lot = app(LotService::class)->register($this->variant, 'L1');
        app(LotService::class)->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $lot);

        $this->actingAs($this->admin)->post(route('inventory.lots.split'), [
            'lot' => $lot->sqid, 'qty' => '4', 'new_lot_no' => 'L1-A',
        ])->assertRedirect();

        $this->assertNotNull(StockLot::query()->where('lot_no', 'L1-A')->first());
    }
}
