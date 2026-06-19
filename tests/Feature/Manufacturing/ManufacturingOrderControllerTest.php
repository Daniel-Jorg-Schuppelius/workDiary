<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ArticleVariant, ManufacturingOrder, ProcedureMaterialRequirement, ProcedureTemplateVersion, User, Warehouse};
use App\Services\Manufacturing\ManufacturingOrderService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungsauftrags-UI (Feature 047): CRUD-Berechtigungen, Anlage, Freigabe und
 * Teilrückmeldung über HTTP.
 */
final class ManufacturingOrderControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('1')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => Article::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $this->product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturable' => true,
            'default_procedure_template_version_id' => $version->id,
        ]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    public function test_index_requires_view_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('manufacturing-orders.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('manufacturing-orders.index'))->assertOk();
    }

    public function test_store_creates_draft_order(): void {
        $response = $this->actingAs($this->admin)->post(route('manufacturing-orders.store'), [
            'article' => $this->product->sqid,
            'target_qty' => '5',
            'unit' => 'Stk',
            'warehouse' => $this->warehouse->sqid,
        ]);

        $order = ManufacturingOrder::query()->firstOrFail();
        $response->assertRedirect(route('manufacturing-orders.show', $order));
        $this->assertSame(ManufacturingOrderStatus::Draft, $order->status);
        $this->assertStringStartsWith('FA-', (string) $order->number);
    }

    public function test_store_forbidden_without_post_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('manufacturing-orders.store'), [
            'article' => $this->product->sqid, 'target_qty' => '1', 'unit' => 'Stk',
        ])->assertForbidden();
    }

    public function test_release_start_report_and_show_render(): void {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );

        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order))->assertRedirect();
        $this->assertSame(ManufacturingOrderStatus::Released, $order->fresh()->status);

        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))->assertOk();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.report', $order), [
            'produced_qty' => '5', 'good_qty' => '5',
        ])->assertRedirect();
        $this->assertSame('5.0000', $order->fresh()->goodTotal());
    }
}
