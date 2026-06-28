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

use App\Models\{Customer, ExternalReference, LexofficeVoucher, Supplier};
use App\Plugins\Lexoffice\{LexofficePlugin, LexofficeVoucherSync};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
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
    private function fakeVoucherlist(array $items): void {
        Http::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => Http::response([
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
        $this->assertSame('119.00', $voucher->total_amount);
        $this->assertSame('2026-05-01', $voucher->voucher_date?->toDateString());
    }

    public function test_sync_assigns_supplier_for_vendor_contact(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->linkContact('contact-v', $supplier);

        $this->fakeVoucherlist([[
            'id' => 'voucher-v',
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

    public function test_sync_is_idempotent_and_archives_disappeared(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->linkContact('contact-1', $customer);

        Http::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => Http::sequence()
                ->push([
                    'content' => [
                        ['id' => 'v1', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'A', 'totalAmount' => 10.0],
                        ['id' => 'v2', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'B', 'totalAmount' => 20.0],
                    ],
                    'totalPages' => 1,
                ], 200)
                ->push([
                    'content' => [
                        ['id' => 'v1', 'voucherType' => 'salesinvoice', 'voucherNumber' => 'A', 'totalAmount' => 15.0],
                    ],
                    'totalPages' => 1,
                ], 200),
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

        $this->fakeVoucherlist([[
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
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/voucherlist')
            && str_contains($request->url(), 'contactId=contact-1'));
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

    public function test_sync_for_unlinked_owner_is_noop(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $result = (new LexofficeVoucherSync('test-key'))->syncFor($customer);

        $this->assertSame(0, $result['contacts']);
        $this->assertSame(0, $result['created']);
        $this->assertDatabaseCount('lexoffice_vouchers', 0);
    }
}
