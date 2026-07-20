<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryGuardsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\User\Permission as P;
use App\Models\{Article, ArticleVariant, User, Warehouse};
use App\Services\Inventory\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollaudit 2026-07: Lager-Schutzmechanismen. M22 — negative Bestände sind eine
 * eigene, auditierte Freigabe (inventory.negative), nicht Teil von
 * inventory.post. M24 — im read_only-Modus (führendes System extern) blockt der
 * Ledger jede lokale Buchung zentral in post().
 */
final class InventoryGuardsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $teamlead;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }

    public function test_negative_issue_requires_dedicated_permission(): void {
        // inventory.post allein reicht nicht: allow_negative verlangt inventory.negative.
        $poster = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $poster->givePermissionTo(P::InventoryPost->value);

        $this->actingAs($poster)
            ->post(route('inventory.movements.store'), $this->movementPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('stock_movements', [
            'article_variant_id' => $this->variant->id,
        ]);
    }

    public function test_negative_issue_with_permission_writes_audit_trail(): void {
        $this->actingAs($this->teamlead)
            ->post(route('inventory.movements.store'), $this->movementPayload())
            ->assertRedirect();

        $this->assertSame('-3.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->teamlead->id,
            'event' => 'inventory.negativeApproved',
            'auditable_type' => ArticleVariant::class,
            'auditable_id' => $this->variant->id,
        ]);
    }

    public function test_read_only_mode_blocks_local_postings(): void {
        $this->organization->forceFill([
            'settings' => array_merge($this->organization->settings ?? [], [
                'inventory_mode' => 'read_only',
                'inventory_plugin_id' => 'mirror-plugin',
            ]),
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('read-only');

        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '5');
    }

    public function test_local_mode_stays_open_after_read_only_org(): void {
        // Instanz-Cache darf eine andere (lokale) Org nicht mitblockieren.
        $ledger = app(InventoryLedger::class);
        $ledger->receipt($this->variant, $this->warehouse, '5');
        $this->assertSame('5.0000', $ledger->available($this->variant, $this->warehouse));
    }

    /** @return array<string, string|int> */
    private function movementPayload(): array {
        return [
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'movement' => 'issue',
            'qty' => '3',
            'ownership' => \App\Enums\Inventory\OwnershipType::Own->value,
            'allow_negative' => 1,
        ];
    }
}
