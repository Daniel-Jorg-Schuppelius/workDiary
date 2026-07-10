<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSyncButtonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, Supplier, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * On-demand-Sync-Button der Lexoffice-Belege auf der Kunden-/Lieferantenansicht.
 */
final class LexofficeVoucherSyncButtonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');
    }

    private function linkContact(string $externalId, Model $model): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $externalId,
            'referenceable_type' => $model->getMorphClass(),
            'referenceable_id' => $model->getKey(),
        ]);
    }

    private function fakeVoucherlist(array $items): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => FakePluginHttp::response(['content' => $items, 'totalPages' => 1], 200),
        ]);
    }

    public function test_customer_sync_button_pulls_vouchers(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-1', $customer);
        $this->fakeVoucherlist([[
            'id' => 'voucher-1', 'voucherType' => 'salesinvoice', 'voucherStatus' => 'open',
            'voucherNumber' => 'RE-1001', 'voucherDate' => '2026-05-01T00:00:00.000+02:00',
            'totalAmount' => 119.00, 'currency' => 'EUR', 'archived' => false,
        ]]);

        $this->actingAs($this->admin)
            ->post(route('customers.lexoffice.sync-vouchers', $customer))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'voucher-1', 'customer_id' => $customer->id,
        ]);
    }

    public function test_supplier_sync_button_pulls_vouchers(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-2', $supplier);
        $this->fakeVoucherlist([[
            'id' => 'voucher-2', 'voucherType' => 'purchaseinvoice', 'voucherStatus' => 'open',
            'voucherNumber' => 'ER-2001', 'voucherDate' => '2026-05-02T00:00:00.000+02:00',
            'totalAmount' => 50.00, 'currency' => 'EUR', 'archived' => false,
        ]]);

        $this->actingAs($this->admin)
            ->post(route('suppliers.lexoffice.sync-vouchers', $supplier))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'voucher-2', 'supplier_id' => $supplier->id,
        ]);
    }

    public function test_sync_button_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)
            ->post(route('customers.lexoffice.sync-vouchers', $customer))
            ->assertForbidden();
    }
}
