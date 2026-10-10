<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeIncomingInvoiceTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Lexoffice;

use App\Enums\Invoicing\{IncomingInvoiceMatchKind, IncomingInvoiceTransferStatus, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer, Invoice};
use App\Models\Platform\{PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficePostingCategory, LexofficeVoucher};
use App\Plugins\Lexoffice\Services\LexofficeIncomingInvoiceTarget;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceMatcher, IncomingInvoiceTransferService};
use App\Services\Stammdaten\CollectiveContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{GeneratesIncomingEInvoices, WithOrganization};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Feature 163, MVP-1111: Rechnungseingang → Lexware Office (E15, E21–E23, E26). */
final class LexofficeIncomingInvoiceTransferTest extends TestCase {
    use GeneratesIncomingEInvoices;
    use RefreshDatabase;
    use WithOrganization;

    private const API = 'https://api.lexware.io/v1';

    private const SELLER_VAT = 'DE123456789';

    private const CONTACT_ID = '3a1f5c6e-0d0b-4c47-9a2b-111111111111';

    private const CATEGORY_DEFAULT = '16d04a28-2f3c-4b1a-9a4b-000000000001';

    private const CATEGORY_PARTY = '16d04a28-2f3c-4b1a-9a4b-000000000002';

    private const VOUCHER_ID = 'b2c3d4e5-0000-4000-8000-000000000001';

    private User $admin;

    /** @var list<string> */
    private array $uploads = [];

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Lieferant GmbH', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Berlin', 'country' => 'DE',
            'vat_id' => self::SELLER_VAT, 'contact_name' => 'Max Muster', 'contact_email' => 'rechnung@lieferant.example',
            'contact_phone' => '+49 30 123456', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX',
            'account_holder' => 'Lieferant GmbH', 'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->connect();
    }

    public function test_assigned_invoice_becomes_an_unchecked_voucher_with_items_and_the_xml(): void {
        $fake = $this->fakeLexware();
        $supplier = $this->linkedSupplier();

        $incoming = $this->receive('ER-1');

        $this->assertSame($supplier->id, $incoming->supplier_id);
        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertSame(self::VOUCHER_ID, $journal->external_id);
        $this->assertNull($journal->error);

        $fake->assertSent(static fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/voucherlist')
            && str_contains((string) $r->getUri(), 'voucherNumber=ER-1') && str_contains((string) $r->getUri(), 'contactId=' . self::CONTACT_ID));
        $body = $this->voucherBody($fake);
        $this->assertSame('purchaseinvoice', $body['type']);
        $this->assertSame('unchecked', $body['voucherStatus']);
        $this->assertSame('gross', $body['taxType']);
        $this->assertSame(self::CONTACT_ID, $body['contactId']);
        $this->assertSame('2026-06-01T00:00:00.000+02:00', $body['voucherDate']);
        $this->assertEquals(238, $body['totalGrossAmount']);
        $this->assertEquals(38, $body['totalTaxAmount']);
        $this->assertEquals([['amount' => 238, 'taxAmount' => 38, 'taxRatePercent' => 19, 'categoryId' => self::CATEGORY_DEFAULT]], $body['voucherItems']);
        $fake->assertSent(static fn (RequestInterface $r): bool => str_ends_with($r->getUri()->getPath(), '/vouchers/' . self::VOUCHER_ID . '/files'));
        $this->assertCount(1, $this->uploads);
        $this->assertStringContainsString('.xml"', $this->uploads[0]);

        $mirror = LexofficeVoucher::query()->sole();
        $this->assertSame($supplier->id, $mirror->supplier_id);
        $this->assertSame('unchecked', $mirror->voucher_status);
    }

    public function test_an_existing_voucher_of_the_same_contact_is_only_linked(): void {
        $fake = $this->fakeLexware(existing: [$this->listed('ER-2', 238.0, 'Lieferant GmbH')]);
        $this->linkedSupplier();

        $this->receive('ER-2');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Linked, $journal->status);
        $this->assertSame('a0000000-0000-4000-8000-000000000001', $journal->external_id);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_an_existing_voucher_with_another_amount_waits(): void {
        $fake = $this->fakeLexware(existing: [$this->listed('ER-3', 99.0, 'Lieferant GmbH')]);
        $this->linkedSupplier();

        $this->receive('ER-3');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame((string) __('lexoffice::incoming.amount_differs', ['number' => 'ER-3']), $journal->error);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_collective_supplier_sends_the_real_name_and_ignores_vouchers_of_other_contacts(): void {
        $fake = $this->fakeLexware(existing: [$this->listed('ER-4', 238.0, 'Ganz andere GmbH')]);
        $incoming = $this->receive('ER-4');
        $this->assertNull($incoming->supplier_id);

        app(IncomingInvoiceMatcher::class)->assign($incoming, app(CollectiveContacts::class)->supplier($this->organization), IncomingInvoiceMatchKind::Manual, $this->admin);

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, IncomingEInvoiceTransfer::query()->sole()->status);
        $fake->assertSent(static fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/voucherlist') && ! str_contains((string) $r->getUri(), 'contactId'));
        $body = $this->voucherBody($fake);
        $this->assertTrue($body['useCollectiveContact']);
        $this->assertSame('Lieferant GmbH', $body['contactName']);
        $this->assertArrayNotHasKey('contactId', $body);
    }

    public function test_the_party_category_wins_and_without_any_category_only_the_header_goes(): void {
        $fake = $this->fakeLexware();
        $supplier = $this->linkedSupplier();
        LexofficePostingCategory::query()->create(['organization_id' => $this->organization->id, 'external_id' => self::CATEGORY_PARTY, 'name' => 'Fremdleistungen', 'kind' => 'outgo']);
        // XML vorab: Der Request bindet eine frisch geladene Organisation, die den Rollenwechsel der Fixture nicht mehr sähe.
        $xml = $this->xml('ER-5');
        $this->post(route('suppliers.lexoffice.posting-category', $supplier), ['category' => self::CATEGORY_PARTY])->assertSessionHas('success');

        $this->store($xml);
        $this->assertSame(self::CATEGORY_PARTY, $this->voucherBody($fake)['voucherItems'][0]['categoryId']);

        $xml = $this->xml('ER-6');
        $this->post(route('suppliers.lexoffice.posting-category', $supplier), ['category' => ''])->assertSessionHas('success');
        $this->assertSame(0, ExternalReference::query()->where('external_type', LexofficeIncomingInvoiceTarget::EXT_TYPE_POSTING_CATEGORY)->count());
        $this->connect(defaultCategory: null);
        $fake = $this->fakeLexware();

        $this->store($xml);

        $body = $this->voucherBody($fake);
        $this->assertArrayNotHasKey('voucherItems', $body);
        $this->assertArrayNotHasKey('totalGrossAmount', $body);
        $journal = IncomingEInvoiceTransfer::query()->latest('id')->firstOrFail();
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertSame((string) __('lexoffice::incoming.header_only.no_category'), $journal->error);
    }

    public function test_the_category_must_fit_the_party(): void {
        $supplier = $this->linkedSupplier();
        LexofficePostingCategory::query()->create(['organization_id' => $this->organization->id, 'external_id' => self::CATEGORY_PARTY, 'name' => 'Erlöse', 'kind' => 'income']);

        $this->post(route('suppliers.lexoffice.posting-category', $supplier), ['category' => self::CATEGORY_PARTY])->assertSessionHasErrors('category');
    }

    public function test_a_contact_without_the_role_waits_instead_of_failing(): void {
        $this->fakeLexware(create: FakePluginHttp::response(['IssueList' => [['i18nKey' => 'invalid_reference', 'source' => 'contactId', 'type' => 'validation_failure']]], 406));
        $supplier = $this->linkedSupplier();

        $this->receive('ER-7');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame((string) __('lexoffice::incoming.contact_role', ['party' => $supplier->name]), $journal->error);
        $this->assertNull($journal->external_id);
    }

    public function test_a_failed_file_upload_is_retried_without_a_second_voucher(): void {
        $fake = $this->fakeLexware(files: [FakePluginHttp::response(['message' => 'kaputt'], 400), FakePluginHttp::response(['id' => 'file-1'], 202)]);
        $this->linkedSupplier();
        $incoming = $this->receive('ER-8');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Failed, $journal->status);
        $this->assertSame(self::VOUCHER_ID, $journal->external_id);

        app(IncomingInvoiceTransferService::class)->retryOpen($this->organization);

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->refresh()->status);
        $this->assertNotNull($incoming->refresh()->transferred_at);
        $creates = array_filter($fake->recorded(), static fn (array $call): bool => $call['request']->getMethod() === 'POST' && str_ends_with($call['request']->getUri()->getPath(), '/vouchers'));
        $this->assertCount(1, $creates);
    }

    public function test_the_switch_keeps_the_target_off(): void {
        $this->connect(transfer: false);
        $fake = $this->fakeLexware();
        $this->linkedSupplier();

        $this->receive('ER-9');

        $this->assertSame(0, IncomingEInvoiceTransfer::query()->count());
        $fake->assertNothingSent();
    }

    private function connect(bool $transfer = true, ?string $defaultCategory = self::CATEGORY_DEFAULT): void {
        PluginSetting::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID],
            ['enabled' => true, 'settings' => array_filter([
                'api_key' => 'test-key', 'request_interval' => '0', 'incoming_transfer' => $transfer, 'incoming_default_category' => $defaultCategory,
            ], static fn (mixed $value): bool => $value !== null)],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<\GuzzleHttp\Psr7\Response>|null  $files
     */
    private function fakeLexware(array $existing = [], ?\GuzzleHttp\Psr7\Response $create = null, ?array $files = null): FakePluginHttp {
        return FakePluginHttp::fake([
            self::API . '/voucherlist*' => FakePluginHttp::response(['content' => $existing, 'totalPages' => 1, 'totalElements' => count($existing), 'first' => true, 'last' => true, 'size' => 25, 'number' => 0, 'numberOfElements' => count($existing)]),
            self::API . '/vouchers/*/files' => $files ?? function (RequestInterface $request): \GuzzleHttp\Psr7\Response {
                // Der Datei-Stream ist nach dem Upload geschlossen: Inhalt hier mitschreiben.
                $this->uploads[] = (string) $request->getBody();

                return FakePluginHttp::response(['id' => 'file-1'], 202);
            },
            self::API . '/vouchers' => $create ?? FakePluginHttp::response(['id' => self::VOUCHER_ID, 'resourceUri' => self::API . '/vouchers/' . self::VOUCHER_ID, 'createdDate' => '2026-10-10T10:00:00.000+02:00', 'updatedDate' => '2026-10-10T10:00:00.000+02:00', 'version' => 1], 201),
        ]);
    }

    /** @return array<string, mixed> */
    private function listed(string $number, float $total, string $contactName): array {
        return [
            'id' => 'a0000000-0000-4000-8000-000000000001', 'voucherType' => 'purchaseinvoice', 'voucherStatus' => 'open',
            'voucherNumber' => $number, 'voucherDate' => '2026-06-01T00:00:00.000+02:00', 'contactName' => $contactName,
            'totalAmount' => $total, 'openAmount' => $total, 'currency' => 'EUR', 'archived' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function voucherBody(FakePluginHttp $fake): array {
        $body = null;
        foreach ($fake->recorded() as $call) {
            if ($call['request']->getMethod() === 'POST' && str_ends_with($call['request']->getUri()->getPath(), '/vouchers')) {
                $body = (string) $call['request']->getBody();
            }
        }
        $this->assertNotNull($body);

        return (array) json_decode($body, true);
    }

    private function linkedSupplier(): Supplier {
        $supplier = Supplier::query()->where('organization_id', $this->organization->id)->where('vat_id', self::SELLER_VAT)->first()
            ?? Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lieferant GmbH', 'vat_id' => self::SELLER_VAT]);
        ExternalReference::query()->updateOrCreate(
            ['plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT, 'referenceable_type' => $supplier->getMorphClass(), 'referenceable_id' => $supplier->id],
            ['organization_id' => $this->organization->id, 'external_id' => self::CONTACT_ID, 'synced_at' => now()],
        );

        return $supplier;
    }

    private function receive(string $number): IncomingEInvoice {
        return $this->store($this->xml($number));
    }

    private function store(string $xml): IncomingEInvoice {
        $incoming = app(IncomingEInvoiceService::class)->storeIncoming($this->admin, $xml, 'application/xml', source: 'mail')['incoming'];
        $this->assertNotNull($incoming);

        return $incoming->refresh();
    }

    private function xml(string $number): string {
        $customer = Customer::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH'], [
            'currency' => 'EUR', 'email' => 'einkauf@muster.example', 'address_street' => 'Kundenweg 7',
            'address_zip' => '54321', 'address_city' => 'Hamburg', 'country' => 'DE', 'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => $number,
            'status' => InvoiceStatus::Issued, 'issued_on' => '2026-06-01', 'due_on' => '2026-06-15', 'currency' => 'EUR',
            'tax_rate' => '19.00', 'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Wartungspauschale', 'quantity' => '2.00', 'unit' => 'Std.', 'unit_price' => '100.00', 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $this->incomingXml($invoice);
    }
}
