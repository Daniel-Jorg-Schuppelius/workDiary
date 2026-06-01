<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMapperContactTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ContactAddress, Customer, Supplier};
use App\Plugins\Lexoffice\LexofficeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LexofficeMapperContactTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeMapper $mapper;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->mapper = new LexofficeMapper;
    }

    public function test_customer_payload_separates_tax_number_and_vat_id(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'company' => 'ACME GmbH',
            'vat_id' => 'DE123456789',
            'tax_number' => '151/815/08150',
        ]);

        $payload = $this->mapper->customerToContactPayload($customer);

        $this->assertArrayHasKey('customer', $payload['roles']);
        $this->assertSame('DE123456789', $payload['company']['vatRegistrationId']);
        $this->assertSame('151/815/08150', $payload['company']['taxNumber']);
    }

    public function test_supplier_payload_uses_vendor_role(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'company' => 'Lieferant GmbH',
            'vat_id' => 'DE987654321',
            'tax_number' => '111/222/33333',
        ]);

        $payload = $this->mapper->supplierToContactPayload($supplier);

        $this->assertArrayHasKey('vendor', $payload['roles']);
        $this->assertArrayNotHasKey('customer', $payload['roles']);
        $this->assertSame('DE987654321', $payload['company']['vatRegistrationId']);
        $this->assertSame('111/222/33333', $payload['company']['taxNumber']);
    }

    public function test_payload_includes_shipping_addresses_from_relation(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'company' => 'ACME GmbH',
            'address_street' => 'Rechnungsweg 1',
            'address_zip' => '10115',
            'address_city' => 'Berlin',
        ]);
        $customer->addresses()->create([
            'organization_id' => $this->organization->id,
            'kind' => ContactAddress::KIND_SHIPPING,
            'street' => 'Lieferweg 2',
            'zip' => '20095',
            'city' => 'Hamburg',
            'country_code' => 'DE',
        ]);

        $payload = $this->mapper->customerToContactPayload($customer);

        $this->assertArrayHasKey('billing', $payload['addresses']);
        $this->assertArrayHasKey('shipping', $payload['addresses']);
        $this->assertSame('Lieferweg 2', $payload['addresses']['shipping'][0]['street']);
        $this->assertSame('Hamburg', $payload['addresses']['shipping'][0]['city']);
    }

    public function test_payload_maps_phone_mobile_and_fax(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'company' => 'ACME GmbH',
            'phone' => '+49 30 111',
            'mobile' => '+49 170 222',
            'fax' => '+49 30 333',
        ]);

        $payload = $this->mapper->customerToContactPayload($customer);

        $this->assertSame(['+49 30 111'], $payload['phoneNumbers']['business']);
        $this->assertSame(['+49 170 222'], $payload['phoneNumbers']['mobile']);
        $this->assertSame(['+49 30 333'], $payload['phoneNumbers']['fax']);
    }
}
