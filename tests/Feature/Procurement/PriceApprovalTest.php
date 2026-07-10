<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceApprovalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, PriceChangeRequest, PricingMarginRule, Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-095: Vier-Augen-Freigabeflow für Verkaufspreisübernahmen.
 * Im direct-Modus bleibt das bisherige Verhalten (Sofort-Übernahme); im
 * four_eyes-Modus entsteht ein Antrag, den nur eine zweite Person genehmigen
 * kann — und nur solange der Vorschlag unverändert ist.
 */
final class PriceApprovalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private User $approver;
    private Supplier $supplier;
    private SupplierCatalogSource $source;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->approver = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'K', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
        ]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true, 'default_sale_price' => '80.0000',
        ]);
        PricingMarginRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'R', 'target_margin' => '50',
            'rounding' => 'none', 'priority' => 0, 'active' => true,
        ]);
    }

    private function linkedItem(string $price = '50.0000'): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube',
            'purchase_price' => $price, 'currency' => 'EUR', 'pack_size' => '1',
            'article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value, 'raw_hash' => 'seed',
        ]);
    }

    private function enableFourEyes(): void {
        $this->organization->update(['settings' => ['pricing' => ['approval_mode' => 'four_eyes']]]);
    }

    public function test_direct_mode_applies_immediately(): void {
        $item = $this->linkedItem(); // EK 50, Zielmarge 50 % → VK 100.00

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.items.apply-price', $item))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('100.0000', $this->article->fresh()->default_sale_price);
        $this->assertSame(0, PriceChangeRequest::query()->count());
    }

    public function test_four_eyes_mode_creates_request_instead_of_applying(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.items.apply-price', $item))
            ->assertRedirect()->assertSessionHas('success');

        // Preis unverändert, offener Antrag mit Snapshot vorhanden.
        $this->assertSame('80.0000', $this->article->fresh()->default_sale_price);
        $request = PriceChangeRequest::query()->firstOrFail();
        $this->assertSame(PriceChangeRequest::STATUS_REQUESTED, $request->status);
        $this->assertSame('100.0000', $request->suggested_price);
        $this->assertSame($this->admin->id, (int) $request->requested_by);
    }

    public function test_four_eyes_request_is_idempotent(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();

        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));

        $this->assertSame(1, PriceChangeRequest::query()->count());
    }

    public function test_approver_must_differ_from_requester(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));
        $request = PriceChangeRequest::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('pricing-margin-rules.approvals.approve', $request))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(PriceChangeRequest::STATUS_REQUESTED, $request->fresh()->status);
        $this->assertSame('80.0000', $this->article->fresh()->default_sale_price);
    }

    public function test_second_person_approval_applies_price(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));
        $request = PriceChangeRequest::query()->firstOrFail();

        $this->actingAs($this->approver)
            ->post(route('pricing-margin-rules.approvals.approve', $request))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('100.0000', $this->article->fresh()->default_sale_price);
        $fresh = $request->fresh();
        $this->assertSame(PriceChangeRequest::STATUS_APPROVED, $fresh->status);
        $this->assertSame($this->approver->id, (int) $fresh->decided_by);
    }

    public function test_stale_request_expires_on_approve(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));
        $request = PriceChangeRequest::query()->firstOrFail();

        // EK ändert sich nach Antragstellung → Vorschlag weicht ab.
        $item->forceFill(['purchase_price' => '60.0000'])->save();

        $this->actingAs($this->approver)
            ->post(route('pricing-margin-rules.approvals.approve', $request))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(PriceChangeRequest::STATUS_EXPIRED, $request->fresh()->status);
        $this->assertSame('80.0000', $this->article->fresh()->default_sale_price);
    }

    public function test_reject_records_note_and_keeps_price(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));
        $request = PriceChangeRequest::query()->firstOrFail();

        $this->actingAs($this->approver)
            ->post(route('pricing-margin-rules.approvals.reject', $request), ['note' => 'Marge zu knapp'])
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $request->fresh();
        $this->assertSame(PriceChangeRequest::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Marge zu knapp', $fresh->decision_note);
        $this->assertSame('80.0000', $this->article->fresh()->default_sale_price);
    }

    public function test_approvals_page_renders_open_requests(): void {
        $this->enableFourEyes();
        $item = $this->linkedItem();
        $this->actingAs($this->admin)->post(route('supplier-catalogs.items.apply-price', $item));

        $this->actingAs($this->approver)
            ->get(route('pricing-margin-rules.approvals'))
            ->assertOk()
            ->assertSee('Schraube');
    }

    public function test_mode_toggle_saves_setting(): void {
        $this->actingAs($this->admin)
            ->post(route('pricing-margin-rules.approval-mode'), ['mode' => 'four_eyes'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('four_eyes', $this->organization->fresh()->pricingApprovalMode());
    }
}
