<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-1019: Suche im Kundenportal — nur freigegebene Bereiche, keine Entwürfe, nur eigene Daten. */
final class PortalSearchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function invoice(Customer $customer, string $number, string $status): void {
        Invoice::create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => $number, 'status' => $status,
            'currency' => 'EUR', 'subtotal' => '100.00', 'tax_rate' => '19.00', 'tax_amount' => '19.00', 'total' => '119.00']);
    }

    public function test_search_respects_capabilities_drafts_and_customer_boundaries(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer, ['invoices']);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->invoice($customer, 'RE-2026-SUCH-1', Invoice::STATUS_ISSUED);
        $this->invoice($customer, 'RE-2026-SUCH-ENTWURF', Invoice::STATUS_DRAFT);
        $this->invoice($other, 'RE-2026-SUCH-FREMD', Invoice::STATUS_ISSUED);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'title' => 'SUCH-Auftrag']);

        $this->actingAs($portalUser, 'customer');
        $this->get(route('customer.search', ['q' => 'SUCH']))->assertOk()
            ->assertSee('RE-2026-SUCH-1')
            ->assertDontSee('RE-2026-SUCH-ENTWURF')
            ->assertDontSee('RE-2026-SUCH-FREMD')
            ->assertDontSee('SUCH-Auftrag');
        $this->get(route('customer.invoices.index'))->assertOk()->assertDontSee('RE-2026-SUCH-ENTWURF');

        $this->allowPortal($customer, ['invoices', 'diary']);
        $this->actingAs($portalUser->fresh(), 'customer');
        $this->get(route('customer.search', ['q' => 'SUCH']))->assertOk()->assertSee('SUCH-Auftrag');
        $this->get(route('customer.search', ['q' => 'x']))->assertOk()->assertDontSee('RE-2026-SUCH-1');
    }
}
