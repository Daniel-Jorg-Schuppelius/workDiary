<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeVoucherSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class LexofficeVoucherSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
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

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function fakeVoucherlist(array $items): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => FakePluginHttp::response([
                'content' => $items,
                'totalPages' => 1,
            ], 200),
        ]);
    }

    public function test_sync_creates_vouchers_for_linked_customer(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->linkContact('contact-1', $customer);

        $this->fakeVoucherlist([[
            'id' => 'voucher-1',
            'contactId' => 'contact-1',
            'voucherType' => 'salesinvoice',
            'voucherStatus' => 'open',
            'voucherNumber' => 'RE-1001',
            'voucherDate' => '2026-05-01T00:00:00.000+02:00',
            'dueDate' => '2026-05-15T00:00:00.000+02:00',
            'totalAmount' => 119.00,
            'openAmount' => 119.00,
            'currency' => 'EUR',
            'archived' => false,
        ]]);

        $result = (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertSame(1, $result['contacts']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('lexoffice_vouchers', [
            'organization_id' => $this->organization->id,
            'external_id' => 'voucher-1',
            'customer_id' => $customer->id,
            'supplier_id' => null,
            'voucher_number' => 'RE-1001',
            'voucher_type' => 'salesinvoice',
        ]);

        $voucher = LexofficeVoucher::query()->where('external_id', 'voucher-1')->firstOrFail();
        $this->assertSame('119.00', $voucher->total_amount?->getAmount());
        $this->assertSame('2026-05-01', $voucher->voucher_date?->toDateString());
        // Die Zeile der VoucherListPage geht vollständig in den Spiegel.
        $this->assertEquals([
            'id' => 'voucher-1',
            'contactId' => 'contact-1',
            'voucherType' => 'salesinvoice',
            'voucherStatus' => 'open',
            'voucherNumber' => 'RE-1001',
            'voucherDate' => '2026-05-01T00:00:00.000+02:00',
            'dueDate' => '2026-05-15T00:00:00.000+02:00',
            'totalAmount' => 119.00,
            'openAmount' => 119.00,
            'currency' => 'EUR',
            'archived' => false,
        ], $voucher->payload);
    }

    public function test_sync_assigns_supplier_for_vendor_contact(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->linkContact('contact-v', $supplier);

        $this->fakeVoucherlist([[
            'id' => 'voucher-v',
            'contactId' => 'contact-v',
            'voucherType' => 'purchaseinvoice',
            'voucherStatus' => 'paid',
            'voucherNumber' => 'ER-9',
            'voucherDate' => '2026-04-02',
            'totalAmount' => 50.0,
            'currency' => 'EUR',
        ]]);

        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'voucher-v',
            'supplier_id' => $supplier->id,
            'customer_id' => null,
            'voucher_type' => 'purchaseinvoice',
        ]);
    }

    public function test_contact_with_both_roles_keeps_purchase_vouchers_off_the_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-both', $customer);
        $this->linkContact('contact-both', $supplier);

        $this->fakeVoucherlist([
            ['id' => 'v-sale', 'contactId' => 'contact-both', 'voucherType' => 'invoice', 'voucherStatus' => 'open', 'voucherNumber' => 'RE-1', 'voucherDate' => '2026-04-02', 'totalAmount' => 119.0, 'currency' => 'EUR'],
            ['id' => 'v-buy', 'contactId' => 'contact-both', 'voucherType' => 'purchaseinvoice', 'voucherStatus' => 'open', 'voucherNumber' => 'ER-1', 'voucherDate' => '2026-04-02', 'totalAmount' => 50.0, 'currency' => 'EUR'],
        ]);

        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-sale', 'customer_id' => $customer->id, 'supplier_id' => null]);
        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-buy', 'customer_id' => null, 'supplier_id' => $supplier->id]);
    }

    public function test_sync_is_idempotent_and_archives_disappeared(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->linkContact('contact-1', $customer);

        FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => [
                FakePluginHttp::response([
                    'content' => [
                        ['id' => 'v1', 'contactId' => 'contact-1', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'A', 'totalAmount' => 10.0],
                        ['id' => 'v2', 'contactId' => 'contact-1', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'B', 'totalAmount' => 20.0],
                    ],
                    'totalPages' => 1,
                ], 200),
                FakePluginHttp::response([
                    'content' => [
                        ['id' => 'v1', 'contactId' => 'contact-1', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'A', 'totalAmount' => 15.0],
                    ],
                    'totalPages' => 1,
                ], 200),
            ],
        ]);

        $first = (new LexofficeVoucherSync('test-key'))->sync($this->organization);
        $this->assertSame(2, $first['created']);

        // Zweiter Lauf: v2 verschwindet → wird archiviert, v1 nur aktualisiert.
        $second = (new LexofficeVoucherSync('test-key'))->sync($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, $second['archived']);

        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v2', 'archived' => true]);
        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v1', 'archived' => false]);
    }

    public function test_sync_for_customer_pulls_only_that_contact(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-1', $customer);

        $fake = $this->fakeVoucherlist([[
            'id' => 'voucher-1', 'voucherType' => 'salesinvoice', 'voucherStatus' => 'open',
            'voucherNumber' => 'RE-1001', 'voucherDate' => '2026-05-01T00:00:00.000+02:00',
            'totalAmount' => 119.00, 'currency' => 'EUR', 'archived' => false,
        ]]);

        $result = (new LexofficeVoucherSync('test-key'))->syncFor($customer);

        $this->assertSame(1, $result['contacts']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'voucher-1', 'customer_id' => $customer->id, 'voucher_number' => 'RE-1001',
        ]);
        $fake->assertSent(fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), '/voucherlist')
            && str_contains((string) $request->getUri(), 'contactId=contact-1'));
        // VoucherListEndpoint::search() mit derselben Query wie vorher roh.
        $this->assertSame(
            ['GET https://api.lexware.io/v1/voucherlist?voucherType=any&voucherStatus=any&contactId=contact-1&page=0&size=250'],
            array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(), $fake->recorded()),
        );
    }

    public function test_sync_for_supplier_assigns_supplier_id(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-2', $supplier);

        $this->fakeVoucherlist([[
            'id' => 'voucher-2', 'voucherType' => 'purchaseinvoice', 'voucherStatus' => 'open',
            'voucherNumber' => 'ER-2001', 'voucherDate' => '2026-05-02T00:00:00.000+02:00',
            'totalAmount' => 50.00, 'currency' => 'EUR', 'archived' => false,
        ]]);

        $result = (new LexofficeVoucherSync('test-key'))->syncFor($supplier);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'voucher-2', 'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * MVP-1112: Belege am Sammelkontakt haben keine contactId und fehlten im
     * Spiegel; die Abfrage ohne Kontakt holt sie ab dem letzten Lauf. Belege
     * mit Kontakt bleiben beim Kontaktlauf.
     */
    public function test_collective_vouchers_are_mirrored_from_the_contactless_query(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-v', $supplier);
        $collective = app(\App\Services\Stammdaten\CollectiveContacts::class)->supplier($this->organization);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => static function (RequestInterface $request): \GuzzleHttp\Psr7\Response {
                $own = ['id' => 'v-kontakt', 'contactId' => 'contact-v', 'voucherType' => 'purchaseinvoice', 'voucherNumber' => 'ER-1', 'totalAmount' => 50.0];

                return str_contains((string) $request->getUri(), 'contactId=')
                    ? FakePluginHttp::response(['content' => [$own], 'totalPages' => 1])
                    : FakePluginHttp::response(['content' => [$own, ['id' => 'v-sammel', 'contactName' => 'Kiosk am Eck', 'voucherType' => 'purchaseinvoice', 'voucherNumber' => 'Q-7', 'totalAmount' => 12.5]], 'totalPages' => 1]);
            },
        ]);

        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-sammel', 'contact_external_id' => null, 'supplier_id' => $collective->id]);
        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-kontakt', 'contact_external_id' => 'contact-v', 'supplier_id' => $supplier->id]);
        $fake->assertNotSent(static fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), 'updatedDateFrom'));

        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $fake->assertSent(static fn (RequestInterface $request): bool => ! str_contains((string) $request->getUri(), 'contactId=')
            && str_contains((string) $request->getUri(), 'updatedDateFrom=' . now()->subDay()->toDateString()));
        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-sammel', 'archived' => false]);
    }

    /** MVP-1117: Belege nicht verknüpfter Kontakte kommen ohne Partei in den Spiegel. */
    public function test_vouchers_of_unlinked_contacts_are_mirrored_and_assigned_once_linked(): void {
        $unlinked = ['id' => 'v-fremd', 'contactId' => 'contact-new', 'contactName' => 'Neukunde GmbH', 'voucherType' => 'invoice', 'voucherStatus' => 'open', 'voucherNumber' => 'RE-77', 'voucherDate' => '2026-04-02', 'totalAmount' => 238.0, 'currency' => 'EUR'];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => static fn (RequestInterface $request): \GuzzleHttp\Psr7\Response => FakePluginHttp::response(['content' => [$unlinked], 'totalPages' => 1]),
        ]);

        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertDatabaseHas('lexoffice_vouchers', [
            'external_id' => 'v-fremd', 'contact_external_id' => 'contact-new', 'contact_name' => 'Neukunde GmbH',
            'customer_id' => null, 'supplier_id' => null,
        ]);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('contact-new', $customer);
        (new LexofficeVoucherSync('test-key'))->sync($this->organization);

        $this->assertDatabaseHas('lexoffice_vouchers', ['external_id' => 'v-fremd', 'customer_id' => $customer->id]);
        $this->assertSame(1, LexofficeVoucher::query()->where('external_id', 'v-fremd')->count());
    }

    public function test_sync_for_unlinked_owner_is_noop(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $result = (new LexofficeVoucherSync('test-key'))->syncFor($customer);

        $this->assertSame(0, $result['contacts']);
        $this->assertSame(0, $result['created']);
        $this->assertDatabaseCount('lexoffice_vouchers', 0);
    }
}
