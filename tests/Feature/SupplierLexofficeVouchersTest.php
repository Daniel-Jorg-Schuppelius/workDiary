<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierLexofficeVouchersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{ExternalReference, LexofficeVoucher, PluginSetting, Supplier, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lieferantenansicht: Lexoffice-Belege (Rechnungen/Aufträge/Angebote …) werden
 * — wie in der Kundenansicht — auf den globalen Header-Zeitraum eingegrenzt.
 */
final class SupplierLexofficeVouchersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_supplier_show_filters_lexoffice_vouchers_by_global_range(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);

        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $supplier->getMorphClass(),
            'referenceable_id' => $supplier->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);

        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-in',
            'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'ER-IN-RANGE', 'voucher_date' => '2026-06-15',
            'total_amount' => '100.00', 'currency' => 'EUR', 'archived' => false,
        ]);
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-out',
            'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'ER-OUT-RANGE', 'voucher_date' => '2026-01-15',
            'total_amount' => '50.00', 'currency' => 'EUR', 'archived' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession([
                'ui.daterange.preset' => 'custom',
                'ui.daterange.from' => '2026-06-01',
                'ui.daterange.to' => '2026-06-30',
            ])
            ->get(route('suppliers.show', $supplier));

        $response->assertOk();
        $response->assertSee('ER-IN-RANGE');
        $response->assertDontSee('ER-OUT-RANGE');
    }
}
