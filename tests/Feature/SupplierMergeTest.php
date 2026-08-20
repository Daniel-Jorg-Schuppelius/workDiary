<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{ExternalReference, PurchaseOrder, Supplier, SupplierMergeDismissal, User, Warehouse};
use App\Services\{SupplierDuplicateFinder, SupplierMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lieferanten-Abgleich (Audit 2026-08, W2.3): Dubletten-Finder auf Basis des
 * vorhandenen SupplierMatchProfile, Zusammenführung über das gemeinsame
 * Merge-Gerüst und die Controller-Oberfläche (Concern MergesDuplicates).
 */
class SupplierMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function supplier(array $attributes = []): Supplier {
        return Supplier::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    public function test_merge_repoints_purchase_orders_and_deletes_source(): void {
        // Ziel bewusst ohne USt-IdNr./E-Mail: nur LEERE Zielfelder werden aus
        // der Quelle aufgefüllt (gefüllte Zielwerte gewinnen immer).
        $target = $this->supplier(['name' => 'Elektro Meier GmbH', 'vendor_number' => 'L-100', 'vat_id' => null, 'email' => null]);
        $source = $this->supplier(['name' => 'Elektro Meier', 'vat_id' => 'DE811111111', 'email' => 'info@meier.test']);

        $warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'code' => 'HL',
            'name' => 'Hauptlager',
        ]);
        $order = PurchaseOrder::create([
            'organization_id' => $this->organization->id,
            'number' => 'BE-9001',
            'supplier_id' => $source->id,
            'warehouse_id' => $warehouse->id,
            'status' => \App\Enums\Procurement\PurchaseOrderStatus::Draft->value,
            'currency' => 'EUR',
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'external_type' => 'contact',
            'referenceable_type' => $source->getMorphClass(),
            'referenceable_id' => $source->id,
            'external_id' => 'lex-contact-1',
        ]);

        app(SupplierMergeService::class)->merge($source, $target);

        $this->assertDatabaseMissing('suppliers', ['id' => $source->id]);
        $this->assertSame((int) $target->id, (int) $order->fresh()->supplier_id);

        // Leere Zielfelder werden aus der Quelle aufgefüllt.
        $target->refresh();
        $this->assertSame('DE811111111', $target->vat_id);
        $this->assertSame('info@meier.test', $target->email);

        // Referenzen zeigen auf das Ziel — ein Re-Import landet beim Überlebenden.
        $this->assertDatabaseHas('external_references', [
            'external_id' => 'lex-contact-1',
            'referenceable_id' => $target->id,
        ]);
    }

    public function test_merge_rejects_self_merge(): void {
        $a = $this->supplier();

        $this->expectException(\InvalidArgumentException::class);
        app(SupplierMergeService::class)->merge($a, $a);
    }

    public function test_merge_rejects_cross_organization(): void {
        $own = $this->supplier();
        // Mandantengrenze: Lieferant einer anderen Organisation.
        $foreign = Supplier::factory()->create(['organization_id' => \App\Models\Organization::factory()->create()->id]);

        $this->expectException(\InvalidArgumentException::class);
        app(SupplierMergeService::class)->merge($foreign, $own);
    }

    public function test_finder_detects_duplicates_by_vat_id_and_respects_dismissals(): void {
        $a = $this->supplier(['name' => 'Meier GmbH', 'vat_id' => 'DE555555555']);
        $b = $this->supplier(['name' => 'Meier', 'vat_id' => 'DE555555555']);

        $candidates = app(SupplierDuplicateFinder::class)->candidates($this->organization);
        $this->assertCount(1, $candidates);

        // Als „kein Duplikat" gemerkt → verschwindet aus den Vorschlägen.
        SupplierMergeDismissal::query()->create(array_merge(
            SupplierMergeDismissal::pairKey((int) $a->id, (int) $b->id),
            ['organization_id' => $this->organization->id, 'dismissed_by' => $this->admin->id],
        ));

        $this->assertCount(0, app(SupplierDuplicateFinder::class)->candidates($this->organization));
    }

    public function test_ui_lists_merges_and_dismisses(): void {
        $target = $this->supplier(['name' => 'Nord Handel GmbH', 'vat_id' => 'DE777777777']);
        $source = $this->supplier(['name' => 'Nord Handel', 'vat_id' => 'DE777777777']);

        $this->actingAs($this->admin)
            ->get(route('suppliers.duplicates.index'))
            ->assertOk()
            ->assertSee('Nord Handel');

        $this->actingAs($this->admin)
            ->get(route('suppliers.duplicates.compare', ['source' => $source->sqid, 'target' => $target->sqid]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('suppliers.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertRedirect(route('suppliers.duplicates.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $source->id]);
    }

    public function test_dismiss_endpoint_records_pair(): void {
        $a = $this->supplier(['name' => 'Süd Bau GmbH', 'vat_id' => 'DE888888888']);
        $b = $this->supplier(['name' => 'Süd Bau', 'vat_id' => 'DE888888888']);

        $this->actingAs($this->admin)
            ->post(route('suppliers.duplicates.dismiss'), ['source' => $b->sqid, 'target' => $a->sqid])
            ->assertRedirect(route('suppliers.duplicates.index'));

        $this->assertDatabaseHas('supplier_merge_dismissals', SupplierMergeDismissal::pairKey((int) $a->id, (int) $b->id));
    }

    public function test_merge_requires_billing_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $target = $this->supplier();
        $source = $this->supplier();

        $this->actingAs($plain)
            ->post(route('suppliers.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertForbidden();

        $this->assertDatabaseHas('suppliers', ['id' => $source->id]);
    }
}
