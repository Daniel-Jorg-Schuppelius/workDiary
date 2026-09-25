<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MobileStocktakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockCount, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{StocktakeService, ValuationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-898: Inventur mobil — jeder Scan addiert, offline über die Sync-Outbox. */
final class MobileStocktakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private StockCount $count;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'name' => 'Dübel 8 mm']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'option_signature' => 'Std', 'sku' => 'DUE-8']);
        app(ValuationService::class)->receipt($variant, $warehouse, '10', '1');
        $this->count = app(StocktakeService::class)->open($warehouse, $this->admin->id);
    }

    public function test_each_scan_adds_to_the_counted_quantity(): void {
        $this->actingAs($this->admin)->get(route('inventory.counts.mobile', $this->count))->assertOk()->assertSee('data-offline-sync="inventory.count"', false);

        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $this->count), ['code' => 'DUE-8', 'qty' => '1'])
            ->assertRedirect(route('inventory.counts.mobile', $this->count));
        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $this->count), ['code' => 'DUE-8', 'qty' => '2.5']);

        $this->assertSame('3.5000', $this->count->lines()->firstOrFail()->counted_qty);
        $this->actingAs($this->admin)->get(route('inventory.counts.mobile', $this->count))->assertSee('Dübel 8 mm');

        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $this->count), ['code' => 'UNBEKANNT', 'qty' => '1'])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $this->count), ['code' => 'DUE-8', 'qty' => '0'])
            ->assertSessionHasErrors('qty');
    }

    public function test_offline_command_adds_once_even_when_flushed_twice(): void {
        $command = ['client_uuid' => (string) Str::uuid(), 'type' => 'inventory.count', 'payload' => ['count' => $this->count->sqid, 'code' => 'DUE-8', 'qty' => '4']];

        $this->actingAs($this->admin)->postJson(route('api.internal.sync.commands'), ['commands' => [$command]])
            ->assertOk()->assertJsonPath('results.0.status', 'applied');
        $this->actingAs($this->admin)->postJson(route('api.internal.sync.commands'), ['commands' => [$command]])
            ->assertOk()->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame('4.0000', $this->count->lines()->firstOrFail()->counted_qty);
    }
}
