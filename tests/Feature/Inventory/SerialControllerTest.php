<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\SerialStatus;
use App\Models\{Article, ArticleVariant, StockSerial, User, Warehouse};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Seriennummern-UI (Feature 047/048, E2): Berechtigungen, Sperren/Entsperren und
 * Geräte-Pass-Verifikation über HTTP.
 */
final class SerialControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private StockSerial $serial;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'serial_required' => true]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->serial = StockSerial::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'serial_no' => 'DEV-0001',
            'status' => SerialStatus::InStock->value,
        ]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('serials.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('serials.index'))->assertOk();
    }

    public function test_block_then_unblock(): void {
        $this->actingAs($this->admin)->post(route('serials.block', $this->serial), ['reason' => 'gestohlen'])->assertRedirect();
        $this->assertSame(SerialStatus::Blocked, $this->serial->fresh()->status);

        $this->actingAs($this->admin)->post(route('serials.unblock', $this->serial))->assertRedirect();
        $this->assertSame(SerialStatus::InStock, $this->serial->fresh()->status);
    }

    public function test_verify_finds_known_serial_and_reports_unknown(): void {
        $this->actingAs($this->admin)->get(route('serials.verify', ['serial' => 'DEV-0001']))
            ->assertOk()->assertSee('DEV-0001');

        $this->actingAs($this->admin)->get(route('serials.verify', ['serial' => 'NOPE-9']))
            ->assertOk()->assertSee(__('inventory.serial.verify.not_found'));
    }
}
