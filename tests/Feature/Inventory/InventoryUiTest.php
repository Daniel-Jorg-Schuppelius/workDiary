<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\StockCountType;
use App\Models\{Article, ArticleVariant, LabelTemplate, StockCount, User, Warehouse};
use App\Services\Inventory\{InventoryLedger, StocktakeService, ValuationService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lager-Oberflächen (Feature 048, E5/E6): Scan-Buchung, Etikettendruck (PDF) und
 * zyklische/Scan-gestützte Inventur über HTTP.
 */
final class InventoryUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'gtin' => 'AGT-1', 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default', 'sku' => 'SKU-1',
        ]);
    }

    public function test_scan_book_receipt_increases_stock(): void {
        $this->actingAs($this->admin)->post(route('inventory.scan.book'), [
            'code' => 'SKU-1', 'action' => 'receipt', 'warehouse' => $this->warehouse->sqid, 'qty' => '5',
        ])->assertRedirect();

        $this->assertSame('5.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    public function test_scan_requires_post_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('inventory.scan.book'), [
            'code' => 'SKU-1', 'action' => 'receipt', 'warehouse' => $this->warehouse->sqid, 'qty' => '5',
        ])->assertForbidden();
    }

    public function test_label_pdf_renders(): void {
        $this->actingAs($this->admin)
            ->get(route('inventory.labels.variant', $this->variant))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_label_respects_org_config(): void {
        $this->organization->update(['settings' => ['label' => ['paper_size' => 'a6', 'with_qr' => false]]]);

        $this->actingAs($this->admin)
            ->get(route('inventory.labels.variant', $this->variant))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_label_template_crud_and_default(): void {
        $this->actingAs($this->admin)->post(route('inventory.label-templates.store'), [
            'name' => 'Klein', 'paper_size' => 'a8', 'orientation' => 'portrait',
            'with_qr' => '1', 'fields' => ['title', 'code'], 'is_default' => '1',
        ])->assertRedirect();

        $tpl = LabelTemplate::query()->firstOrFail();
        $this->assertTrue($tpl->is_default);
        $this->assertSame(['title', 'code'], $tpl->fields);

        // Etikett nutzt die Standardvorlage.
        $this->actingAs($this->admin)->get(route('inventory.labels.variant', $this->variant))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin)->get(route('inventory.label-templates.index'))->assertOk()->assertSee('Klein');
    }

    public function test_cycle_count_opens_for_class(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '1');

        $this->actingAs($this->admin)->post(route('inventory.counts.cycle'), [
            'warehouse' => $this->warehouse->sqid, 'abc_class' => 'C',
        ])->assertRedirect();

        $count = StockCount::query()->firstOrFail();
        $this->assertSame(StockCountType::Cycle, $count->count_type);
    }

    public function test_record_scan_on_count(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '1');
        $count = app(StocktakeService::class)->openCycle($this->warehouse, [$this->variant->id]);

        $this->actingAs($this->admin)->post(route('inventory.counts.scan', $count), [
            'code' => 'SKU-1', 'qty' => '9',
        ])->assertRedirect();

        $this->assertSame('9.0000', $count->lines()->firstOrFail()->counted_qty);
    }
}
