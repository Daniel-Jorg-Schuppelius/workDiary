<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimReturnLabelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\Claims\{ClaimCase, ClaimRmaReturn};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Shipping\{CarrierConnection, Shipment};
use App\Services\Claims\{ClaimCaseService, ClaimRmaService};
use App\Services\Shipping\ShippingProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeShippingProvider;
use Tests\TestCase;

/** MVP-917: Retourenlabel einer RMA über das Versandmodul. */
final class ClaimReturnLabelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private FakeShippingProvider $provider;

    private ClaimCase $case;

    private ClaimRmaReturn $rma;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization(['settings' => ['einvoice' => ['seller_name' => 'WorkDiary GmbH', 'street' => 'Werkstr. 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE']]]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->provider = new FakeShippingProvider('mock');
        app(ShippingProviderRegistry::class)->register($this->provider);
        CarrierConnection::query()->create(['organization_id' => $this->organization->id, 'carrier' => 'mock', 'name' => 'Mock-Carrier', 'credentials' => ['username' => 'u', 'password' => 'p'], 'sandbox' => true, 'active' => true]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH', 'address_street' => 'Teststr. 1', 'address_zip' => '80331', 'address_city' => 'München', 'country' => 'DE']);
        $this->case = app(ClaimCaseService::class)->open($this->organization, $this->admin, ['title' => 'Pumpe defekt', 'source' => 'manual', 'priority' => 'normal', 'severity' => 'minor', 'customer_id' => $customer->id]);
        $this->rma = app(ClaimRmaService::class)->announce($this->case, []);
    }

    public function test_return_label_goes_from_customer_to_organization_and_can_be_downloaded(): void {
        $this->actingAs($this->admin)->get(route('claims.show', $this->case))->assertOk()->assertSee(route('claims.rma.return-label', $this->rma));

        $this->actingAs($this->admin)->post(route('claims.rma.return-label', $this->rma), ['carrier' => 'mock', 'weight_grams' => 1200])
            ->assertRedirect()->assertSessionHas('status');

        $request = $this->provider->lastRequest;
        $this->assertNotNull($request);
        $this->assertSame('Berlin', $request->recipient->city);
        $this->assertSame('München', $request->returnFrom?->city);
        $this->assertSame($this->rma->rma_number, $request->reference);
        $this->assertSame(1200, $request->packages[0]->weightGrams);

        $shipment = Shipment::query()->sole();
        $this->assertTrue($shipment->is_return);
        $this->assertSame($this->rma->id, $shipment->claim_rma_return_id);
        $this->assertSame(ShipmentStatus::Labeled, $shipment->status);

        $this->actingAs($this->admin)->get(route('claims.show', $this->case))->assertOk()->assertSee((string) $shipment->tracking_number);
        $this->actingAs($this->admin)->get(route('claims.rma.return-label.download', [$this->rma, $shipment]))->assertOk()->assertDownload();
    }

    public function test_unknown_carrier_is_refused_and_leaves_no_draft(): void {
        $this->actingAs($this->admin)->post(route('claims.rma.return-label', $this->rma), ['carrier' => 'dhl', 'weight_grams' => 1000])->assertSessionHas('error');
        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_return_label_needs_the_warehouse_permission(): void {
        $support = User::factory()->support()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($support)->post(route('claims.rma.return-label', $this->rma), ['carrier' => 'mock', 'weight_grams' => 1000])->assertForbidden();
    }
}
