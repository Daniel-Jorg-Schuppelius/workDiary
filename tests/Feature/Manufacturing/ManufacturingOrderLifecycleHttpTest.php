<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderLifecycleHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Enums\User\Permission;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureMaterialRequirement, ProcedureRun, ProcedureTemplateVersion};
use App\Services\Manufacturing\ManufacturingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungsauftrag abschließen, warten, sperren, wieder aufnehmen (MVP-1493,
 * ul-03): `transition()` hatte nur den Storno als Aufrufer — Aufträge blieben
 * „in Arbeit“, die Nachkalkulation leer und der Fertigungsnachweis entstand nie.
 */
final class ManufacturingOrderLifecycleHttpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Warehouse $warehouse;

    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
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
            'name' => 'Schaltschrank Nord',
            'default_procedure_template_version_id' => $version->id,
        ]);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->product->id, 'is_default' => true, 'option_signature' => 'default']);
    }

    private function runningOrder(): ManufacturingOrder {
        $order = app(ManufacturingOrderService::class)->createDraft($this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id]);
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order));
        $this->actingAs($this->admin)->post(route('manufacturing-orders.start', $order));
        $order->refresh();
        $this->assertSame(ManufacturingOrderStatus::InProgress, $order->status);

        return $order;
    }

    public function test_completing_needs_a_reported_good_quantity(): void {
        $order = $this->runningOrder();
        $this->actingAs($this->admin)->post(route('manufacturing-orders.report', $order), ['produced_qty' => '2', 'good_qty' => '0', 'scrap_qty' => '2']);

        $this->actingAs($this->admin)->post(route('manufacturing-orders.complete', $order))
            ->assertSessionHasErrors(['good_qty' => 'Ein Auftrag lässt sich erst abschließen, wenn eine Gutmenge zurückgemeldet ist.']);

        $order->refresh();
        $this->assertSame(ManufacturingOrderStatus::InProgress, $order->status);
        $this->assertNull($order->completed_at);
    }

    public function test_completing_freezes_the_record_and_shows_up_in_the_costing(): void {
        $order = $this->runningOrder();
        $this->actingAs($this->admin)->post(route('manufacturing-orders.report', $order), ['produced_qty' => '5', 'good_qty' => '5']);

        $this->actingAs($this->admin)->post(route('manufacturing-orders.complete', $order))
            ->assertRedirect(route('manufacturing-orders.show', $order))
            ->assertSessionHas('success', 'Auftrag abgeschlossen.');

        $order->refresh();
        $this->assertSame(ManufacturingOrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertTrue(DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_type', $order->getMorphClass())->where('documentable_id', $order->id)
            ->where('document_kind', RenderDocumentKind::ManufacturingRecord->value)->exists(), 'Fertigungsnachweis eingefroren');

        $this->actingAs($this->admin)->get(route('articles.costing', $this->product))->assertOk()
            ->assertSee((string) $order->number)
            ->assertDontSee('Keine abgeschlossenen Fertigungsaufträge im Zeitraum.');
        // Abgeschlossen ist terminal: keine Aktionen mehr an der Statusmaschine.
        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))->assertOk()
            ->assertDontSee(route('manufacturing-orders.complete', $order), false)
            ->assertDontSee(route('manufacturing-orders.wait', $order), false);
    }

    public function test_wait_block_and_resume_keep_the_procedure_run(): void {
        $order = $this->runningOrder();
        $runId = $order->procedure_run_id;

        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))->assertOk()
            ->assertSee(route('manufacturing-orders.complete', $order), false)
            ->assertSee(route('manufacturing-orders.wait', $order), false)
            ->assertSee(route('manufacturing-orders.block', $order), false)
            ->assertDontSee(route('manufacturing-orders.resume', $order), false)
            ->assertDontSee(route('manufacturing-orders.start', $order), false);

        $this->actingAs($this->admin)->post(route('manufacturing-orders.wait', $order))->assertSessionHas('success', 'Auftrag wartet.');
        $this->assertSame(ManufacturingOrderStatus::Waiting, $order->fresh()?->status);
        $this->actingAs($this->admin)->get(route('manufacturing-orders.show', $order))->assertOk()
            ->assertSee('Wieder aufnehmen')
            ->assertDontSee(route('manufacturing-orders.start', $order), false)
            ->assertDontSee(route('manufacturing-orders.complete', $order), false);

        $this->actingAs($this->admin)->post(route('manufacturing-orders.resume', $order))->assertSessionHas('success', 'Auftrag wieder in Arbeit.');
        $this->actingAs($this->admin)->post(route('manufacturing-orders.block', $order))->assertSessionHas('success', 'Auftrag gesperrt.');
        $this->assertSame(ManufacturingOrderStatus::Blocked, $order->refresh()->status);
        $this->actingAs($this->admin)->post(route('manufacturing-orders.resume', $order));

        $order->refresh();
        $this->assertSame(ManufacturingOrderStatus::InProgress, $order->status);
        $this->assertSame($runId, $order->procedure_run_id, 'Wiederaufnahme legt keinen zweiten Prozedurlauf an');
        $this->assertSame(1, ProcedureRun::query()->count());
    }

    public function test_transitions_outside_the_status_machine_are_refused(): void {
        $order = app(ManufacturingOrderService::class)->createDraft($this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id]);
        $this->actingAs($this->admin)->post(route('manufacturing-orders.release', $order));

        $this->actingAs($this->admin)->post(route('manufacturing-orders.wait', $order))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('manufacturing-orders.resume', $order))
            ->assertSessionHas('error', 'Nur wartende oder gesperrte Aufträge lassen sich wieder aufnehmen.');
        $this->actingAs($this->admin)->post(route('manufacturing-orders.complete', $order));

        $this->assertSame(ManufacturingOrderStatus::Released, $order->fresh()?->status);
    }

    public function test_lifecycle_actions_need_the_post_permission(): void {
        $order = $this->runningOrder();
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::InventoryViewAny->value);

        foreach (['complete', 'wait', 'block', 'resume'] as $action) {
            $this->actingAs($viewer)->post(route('manufacturing-orders.' . $action, $order))->assertForbidden();
        }
        $this->assertSame(ManufacturingOrderStatus::InProgress, $order->fresh()?->status);
    }
}
