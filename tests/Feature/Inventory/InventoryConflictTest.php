<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\Inventory\StockState;
use App\Enums\User\Permission as P;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryConflictResolver, InventoryLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-072: Auflösung kompensationspflichtiger Inventory-Outbox-Konflikte —
 * lokal belassen oder per Gegenbuchung ausgleichen; Reiter mit Zähler
 * (Entscheidung 2026-10-06). Artikelkonflikte sind mit dem
 * Lexoffice-Rückschreiben entfallen (MVP-1519).
 */
final class InventoryConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
    }

    private function bookedMovement(): StockMovement {
        return $this->ledger->receipt($this->variant, $this->warehouse, '50');
    }

    private function conflictFor(StockMovement $movement): PendingExternalConflict {
        return PendingExternalConflict::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl',
            'conflict_type' => 'inventory_outbox',
            'referenceable_type' => $movement->getMorphClass(),
            'referenceable_id' => $movement->id,
            'local_snapshot' => ['qty_base' => $movement->qty_base, 'stock_state' => $movement->stock_state->value],
            'remote_snapshot' => [],
            'status' => ExternalConflictStatus::Open,
        ]);
    }

    private function physical(): string {
        return $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical);
    }

    public function test_compensate_reverses_movement_and_closes_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $this->assertSame('50.0000', $this->physical());

        app(InventoryConflictResolver::class)->compensate($conflict, null);

        $this->assertSame('0.0000', $this->physical()); // Gegenbuchung hebt auf
        $this->assertSame(ExternalConflictStatus::Compensated, $conflict->fresh()->status);
    }

    public function test_keep_local_closes_conflict_without_reversal(): void {
        $conflict = $this->conflictFor($this->bookedMovement());

        app(InventoryConflictResolver::class)->keepLocal($conflict, null);

        $this->assertSame('50.0000', $this->physical()); // Bestand unverändert
        $this->assertSame(ExternalConflictStatus::ResolvedLocal, $conflict->fresh()->status);
    }

    public function test_compensate_rejects_already_resolved_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $conflict->forceFill(['status' => ExternalConflictStatus::Compensated])->save();

        $this->expectException(RuntimeException::class);
        app(InventoryConflictResolver::class)->compensate($conflict, null);
    }

    public function test_compensate_route_books_counter_movement(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $conflict = $this->conflictFor($this->bookedMovement());

        $this->actingAs($admin)
            ->post(route('inventory.conflicts.compensate', $conflict))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('0.0000', $this->physical());
        $this->assertSame(ExternalConflictStatus::Compensated, $conflict->fresh()->status);
    }

    /** Die Liste verkettete den Status früher mit dem Textschlüssel und wählte den Ton über Zeichenketten. */
    public function test_index_shows_label_and_tone_per_status(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $open = $this->conflictFor($this->bookedMovement());
        $compensated = $this->conflictFor($this->bookedMovement());
        $compensated->forceFill(['status' => ExternalConflictStatus::Compensated])->save();
        $kept = $this->conflictFor($this->bookedMovement());
        $kept->forceFill(['status' => ExternalConflictStatus::ResolvedLocal])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('inventory.conflicts.index', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/badge-warning"[^>]*>\s*Offen\s*</', $html);
        $this->assertMatchesRegularExpression('/badge-info"[^>]*>\s*Kompensiert\s*</', $html);
        $this->assertMatchesRegularExpression('/badge-success"[^>]*>\s*Lokal belassen\s*</', $html);
        // Auflösen bietet nur der offene Konflikt an.
        $this->assertSame(1, substr_count($html, route('inventory.conflicts.compensate', $open)));
        $this->assertStringNotContainsString(route('inventory.conflicts.compensate', $compensated), $html);
    }

    public function test_keep_local_rejects_already_resolved_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $conflict->forceFill(['status' => ExternalConflictStatus::ResolvedLocal])->save();

        $this->expectException(RuntimeException::class);
        app(InventoryConflictResolver::class)->keepLocal($conflict, null);
    }

    private function admin(): User {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function userWithPermissions(P ...$permissions): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        foreach ($permissions as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }

        return $user;
    }

    public function test_stock_resolutions_are_audited(): void {
        $admin = $this->admin();
        $kept = $this->conflictFor($this->bookedMovement());
        $compensated = $this->conflictFor($this->bookedMovement());

        $this->actingAs($admin)->post(route('inventory.conflicts.keep-local', $kept))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('inventory.conflicts.compensate', $compensated))->assertSessionHasNoErrors();

        $statuses = AuditLog::query()->where('event', 'integration.conflict_resolved')->orderBy('id')->get()
            ->mapWithKeys(fn (AuditLog $log): array => [(int) $log->auditable_id => $log->changes['status']])->all();
        $this->assertSame([$kept->id => 'resolved_local', $compensated->id => 'compensated'], $statuses);
    }

    /** Andere Konfliktarten zeigt die Liste nie, und die Bestandswege greifen dort nicht. */
    public function test_other_conflict_types_stay_out_of_the_list_and_the_stock_paths(): void {
        $admin = $this->admin();
        $stock = $this->conflictFor($this->bookedMovement());
        $other = PendingExternalConflict::query()->create(['conflict_type' => 'contact', 'referenceable_type' => 'customers', 'referenceable_id' => 1] + $stock->only(['organization_id', 'plugin_id', 'local_snapshot', 'remote_snapshot', 'status']));

        $listed = $this->actingAs($admin)->get(route('inventory.conflicts.index'))->assertOk()->viewData('conflicts')->pluck('id')->all();
        $this->assertSame([$stock->id], $listed);
        $this->actingAs($admin)->post(route('inventory.conflicts.compensate', $other))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('inventory.conflicts.keep-local', $other))->assertSessionHas('error');
        $this->assertSame(ExternalConflictStatus::Open, $other->fresh()->status);
    }

    /** Der Reiter zählt nur offene Konflikte; ohne offene gibt es keinen Zähler. */
    public function test_inventory_tabs_show_the_conflicts_tab_with_the_open_count(): void {
        $admin = $this->admin();
        $this->conflictFor($this->bookedMovement());
        $this->conflictFor($this->bookedMovement())->forceFill(['status' => ExternalConflictStatus::ResolvedLocal])->save();

        $html = (string) $this->actingAs($admin)->get(route('inventory.stock'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('inventory.conflicts.index'), '#') . '"[^>]*>\s*<span[^>]*>difference</span>\s*Konflikte\s*<span class="badge badge-sm ml-2">1</span>#', $html);

        $html = (string) $this->actingAs($admin)->get(route('inventory.conflicts.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('inventory.conflicts.index'), '#') . '"\s+class="tab gap-1 tab-active"#', $html);

        PendingExternalConflict::query()->update(['status' => ExternalConflictStatus::Compensated->value]);
        $html = (string) $this->actingAs($admin)->get(route('inventory.stock'))->assertOk()->getContent();
        $this->assertStringContainsString('Konflikte', $html);
        $this->assertStringNotContainsString('badge badge-sm ml-2', $html);
    }

    /** Sehen mit dem Bestands-Leserecht, auflösen mit dem Buchungsrecht; das Artikelrecht öffnet die Liste nicht mehr. */
    public function test_viewing_and_resolving_need_the_stock_rights(): void {
        $stock = $this->conflictFor($this->bookedMovement());

        $this->actingAs($this->userWithPermissions(P::ArticleViewAny, P::ArticleManage))->get(route('inventory.conflicts.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization->id]))->get(route('inventory.conflicts.index'))->assertForbidden();

        $reader = $this->userWithPermissions(P::InventoryViewAny);
        $this->actingAs($reader)->get(route('inventory.conflicts.index'))->assertOk()
            ->assertDontSee(route('inventory.conflicts.compensate', $stock), false);
        $this->actingAs($reader)->post(route('inventory.conflicts.compensate', $stock))->assertForbidden();
        $this->actingAs($reader)->post(route('inventory.conflicts.keep-local', $stock))->assertForbidden();

        // MVP-1585: Kompensieren ist ein eigenes Recht, nicht mehr Teil des Buchens.
        $poster = $this->userWithPermissions(P::InventoryViewAny, P::InventoryExternalResolve);
        $this->actingAs($poster)->get(route('inventory.conflicts.index'))->assertOk()
            ->assertSee('action="' . route('inventory.conflicts.compensate', $stock) . '"', false);

        $this->assertSame(ExternalConflictStatus::Open, $stock->fresh()->status);
        $this->assertSame('50.0000', $this->physical());
    }
}
