<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalReturnTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Enums\Claims\{ClaimRmaStatus, ClaimSource};
use App\Enums\CustomerPortal\PortalCapability;
use App\Enums\Inventory\SerialStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Attachments\Attachment;
use App\Models\Claims\ClaimCase;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockSerial, Warehouse};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-935: Rücksendung im Kundenportal anmelden. */
final class PortalReturnTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private StockDelivery $delivery;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Heizlüfter']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'HL-1']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->delivery = StockDelivery::query()->create([
            'organization_id' => $this->organization->id, 'article_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $this->customer->id, 'quantity' => '2', 'unit' => 'Stk', 'name_snapshot' => 'Heizlüfter', 'sku_snapshot' => 'HL-1',
            'stock_status' => 'delivered', 'facturation_status' => 'pending', 'delivered_at' => now()->subWeek(),
        ]);
        StockSerial::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id, 'article_variant_id' => $variant->id,
            'serial_no' => 'SN-7', 'status' => SerialStatus::Shipped->value, 'stock_delivery_id' => $this->delivery->id, 'customer_id' => $this->customer->id,
        ]);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
    }

    public function test_customer_registers_a_return_with_serial_and_photo(): void {
        $this->allowPortal($this->customer, [PortalCapability::Claims->value, PortalCapability::Returns->value]);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.returns.create'))->assertOk()->assertSee('Heizlüfter')->assertSee('SN-7');

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.returns.store'), [
            'delivery_id' => $this->delivery->sqid, 'serial_no' => 'SN-7', 'quantity' => '1',
            'title' => 'Gerät heizt nicht', 'description' => 'Nach zwei Tagen kein Heizbetrieb mehr.',
            'photos' => [UploadedFile::fake()->image('foto.jpg')],
        ])->assertRedirect()->assertSessionHas('status');

        $claim = ClaimCase::query()->sole();
        $this->assertSame(ClaimSource::Portal, $claim->source);
        $this->assertSame($this->customer->id, $claim->customer_id);
        $this->assertSame('SN-7', $claim->serial_no);
        $this->assertNotNull($claim->stock_serial_id);
        $rma = $claim->rmaReturns()->sole();
        $this->assertSame(ClaimRmaStatus::Announced, $rma->status);
        $this->assertSame(1, Attachment::query()->where('attachable_id', $claim->id)->count());
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.claims.show', $claim))->assertOk()->assertSee($rma->rma_number);
    }

    public function test_foreign_serials_and_missing_subject_are_rejected(): void {
        $this->allowPortal($this->customer, [PortalCapability::Returns->value]);
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.returns.store'), [
            'delivery_id' => $this->delivery->sqid, 'serial_no' => 'SN-FREMD', 'title' => 'x', 'description' => 'Beschreibung lang genug.',
        ])->assertSessionHasErrors('serial_no');

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = $this->delivery->replicate()->fill(['customer_id' => $other->id]);
        $foreign->save();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.returns.store'), [
            'delivery_id' => $foreign->sqid, 'title' => 'x', 'description' => 'Beschreibung lang genug.',
        ])->assertSessionHasErrors('delivery_id');
        $this->assertSame(0, ClaimCase::query()->count());
    }

    public function test_capability_is_required(): void {
        $this->allowPortal($this->customer, [PortalCapability::Claims->value]);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.returns.create'))->assertNotFound();
    }
}
