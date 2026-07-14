<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierScorecardControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleVariant, Organization, PurchaseOrder, PurchaseOrderLine, StockMovement, Supplier, User, Warehouse};
use App\Models\Claims\ClaimCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Lieferantenperformance-Scorecards (Bauturbo Welle D) über HTTP: Recht,
 * Ranking-/Detail-Rendering, signierte Beleg-Drilldowns (Signatur-403) und
 * Mandantentrennung.
 */
final class SupplierScorecardControllerTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta Bauzentrum']);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
        $this->seedOrderWithReceipt('2026-06-10', '2026-06-05', '2026-06-01');
    }

    private function rangeSession(): array {
        return $this->dateRangeSession('2026-06-01', '2026-06-30');
    }

    public function test_index_renders_for_admin(): void {
        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get(route('supplier-scorecards.index'))
            ->assertOk()
            ->assertSee('Beta Bauzentrum');
    }

    public function test_requires_authentication(): void {
        $this->get(route('supplier-scorecards.index'))->assertRedirect(route('login'));
    }

    public function test_forbidden_without_inventory_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->rangeSession())
            ->get(route('supplier-scorecards.index'))
            ->assertForbidden();
    }

    public function test_show_renders_detail(): void {
        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get(route('supplier-scorecards.show', $this->supplier))
            ->assertOk()
            ->assertSee('Beta Bauzentrum');
    }

    public function test_drilldown_requires_valid_signature(): void {
        // Ohne Signatur → 403 (die Route-Bindung greift, dann bricht der
        // Controller mit hasValidSignature ab).
        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get(route('supplier-scorecards.drilldown', [$this->supplier, 'kind' => 'deliveries', 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertForbidden();
    }

    public function test_drilldown_with_valid_signature_ok(): void {
        $url = $this->signedDrilldown('deliveries');

        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get($url)
            ->assertOk();
    }

    public function test_drilldown_tampered_signature_is_forbidden(): void {
        $url = $this->signedDrilldown('deliveries');

        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get($url . '&injected=1')
            ->assertForbidden();
    }

    public function test_drilldown_forbidden_without_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $url = $this->signedDrilldown('claims');

        // Gültige Signatur, aber kein Einkaufs-/Report-Recht → 403.
        $this->actingAs($plain)
            ->withSession($this->rangeSession())
            ->get($url)
            ->assertForbidden();
    }

    public function test_organization_isolation_blocks_foreign_supplier(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = Supplier::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->admin)
            ->withSession($this->rangeSession())
            ->get(route('supplier-scorecards.show', $foreign))
            ->assertNotFound();
    }

    private function signedDrilldown(string $kind): string {
        return URL::temporarySignedRoute(
            'supplier-scorecards.drilldown',
            now()->addMinutes(30),
            ['supplier' => $this->supplier, 'kind' => $kind, 'from' => '2026-06-01', 'to' => '2026-06-30'],
        );
    }

    private function seedOrderWithReceipt(string $expectedAt, string $deliveredAt, string $orderedAt): void {
        $order = PurchaseOrder::create([
            'organization_id' => $this->organization->id,
            'number' => 'BE-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseOrderStatus::Received->value,
            'currency' => 'EUR',
            'ordered_at' => $orderedAt,
            'expected_at' => $expectedAt,
        ]);
        $line = PurchaseOrderLine::create([
            'organization_id' => $this->organization->id,
            'purchase_order_id' => $order->id,
            'article_id' => $this->article->id,
            'article_variant_id' => $this->variant->id,
            'description' => 'Position',
            'ordered_qty' => '10', 'received_qty' => '10', 'unit' => 'Stk',
            'unit_price' => '10.00', 'currency' => 'EUR',
        ]);
        StockMovement::create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_state' => StockState::Physical->value,
            'ownership_type' => OwnershipType::Own->value,
            'movement_type' => StockMovementType::Receipt->value,
            'qty_base' => '10',
            'occurred_at' => CarbonImmutable::parse($deliveredAt),
            'source_type' => $line->getMorphClass(),
            'source_id' => $line->id,
            'currency' => 'EUR',
        ]);
        ClaimCase::create([
            'organization_id' => $this->organization->id,
            'number' => 'RK-0001',
            'status' => \App\Enums\Claims\ClaimStatus::Received->value,
            'source' => \App\Enums\Claims\ClaimSource::Internal->value,
            'priority' => 'normal', 'severity' => 'minor', 'title' => 'Transportschaden',
            'supplier_id' => $this->supplier->id,
            'reported_at' => CarbonImmutable::parse('2026-06-12'),
        ]);
    }
}
