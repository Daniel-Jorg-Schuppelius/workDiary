<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskPortalTicketServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\Sales\ServiceOffering;
use App\Models\ServiceTicket\{BusinessService, ServiceQueue, ServiceTicket};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Portal-Ticket mit Asset- und Serviceauswahl (MVP-1261): angeboten wird nur,
 * was der Kunde sieht; alles andere endet 422.
 */
final class HelpdeskPortalTicketServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Portal', 'visibility' => 'portal']);
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);
    }

    /** @return array{0: BusinessService, 1: ServiceOffering} */
    private function offering(bool $portal): array {
        $service = BusinessService::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Arbeitsplatz']);
        $offering = ServiceOffering::query()->create([
            'organization_id' => $this->organization->id, 'business_service_id' => $service->id,
            'name' => $portal ? 'Notebook' : 'Intern', 'active' => true,
        ]);
        RequestItem::query()->create([
            'organization_id' => $this->organization->id, 'service_offering_id' => $offering->id,
            'name' => $offering->name . ' bestellen', 'visibility' => $portal ? ['portal' => true] : null,
        ]);

        return [$service, $offering];
    }

    public function test_customer_picks_own_asset_and_a_visible_offering(): void {
        [$service, $offering] = $this->offering(true);
        [, $hidden] = $this->offering(false);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Laptop Empfang']);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.tickets.index'))
            ->assertOk()
            ->assertSee('Laptop Empfang')
            ->assertSee('value="' . $offering->sqid . '"', false)
            ->assertDontSee('value="' . $hidden->sqid . '"', false);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.tickets.store'), [
            'title' => 'Laptop startet nicht', 'asset' => $asset->sqid, 'offering' => $offering->sqid,
        ])->assertRedirect();

        $ticket = ServiceTicket::query()->latest('id')->firstOrFail();
        $this->assertSame([(int) $asset->id, (int) $service->id, (int) $offering->id], [(int) $ticket->asset_id, (int) $ticket->business_service_id, (int) $ticket->service_offering_id]);
    }

    public function test_foreign_asset_or_hidden_offering_is_rejected(): void {
        [, $hidden] = $this->offering(false);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignAsset = Asset::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $other->id]);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.tickets.store'), ['title' => 'Fremdes Gerät', 'asset' => $foreignAsset->sqid])
            ->assertStatus(422);
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.tickets.store'), ['title' => 'Verstecktes Angebot', 'offering' => $hidden->sqid])
            ->assertStatus(422);
        $this->assertSame(0, ServiceTicket::query()->count());
    }
}
