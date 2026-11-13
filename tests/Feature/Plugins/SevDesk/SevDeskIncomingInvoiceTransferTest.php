<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskIncomingInvoiceTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\{IncomingInvoiceMatchKind, IncomingInvoiceTransferStatus, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer, Invoice};
use App\Models\Platform\{PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Plugins\SevDesk\Services\SevDeskIncomingInvoiceTarget;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceMatcher};
use App\Services\Stammdaten\CollectiveContacts;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Storage};
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{GeneratesIncomingEInvoices, WithOrganization};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Feature 163, MVP-1507: Rechnungseingang → sevDesk (Entwurf mit Original, Dubletten, Wartegründe). */
final class SevDeskIncomingInvoiceTransferTest extends TestCase {
    use GeneratesIncomingEInvoices;
    use RefreshDatabase;
    use WithOrganization;

    private const string API = 'https://my.sevdesk.de/api/v1';

    private const string SELLER_VAT = 'DE123456789';

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

    public function test_an_assigned_invoice_becomes_a_draft_voucher_with_the_xml_and_a_mirror_row(): void {
        $fake = $this->fakeSevDesk();
        $supplier = $this->linkedSupplier();

        $incoming = $this->receive('ER-1');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertSame('5501', $journal->external_id);
        $this->assertSame(SevDeskPlugin::ID, $journal->target);
        $fake->assertSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'GET' && $r->getUri()->getPath() === '/api/v1/Voucher'
            && str_contains(urldecode($r->getUri()->getQuery()), 'descriptionLike=ER-1')
            && str_contains(urldecode($r->getUri()->getQuery()), 'creditDebit=C')
            && str_contains(urldecode($r->getUri()->getQuery()), 'contact[id]=7001'));

        $body = $this->saveBody($fake);
        $this->assertSame('50', $body['voucher']['status']);
        $this->assertSame('C', $body['voucher']['creditDebit']);
        $this->assertSame('VOU', $body['voucher']['voucherType']);
        $this->assertSame(['id' => '9', 'objectName' => 'TaxRule'], $body['voucher']['taxRule']);
        $this->assertSame(['id' => 7001, 'objectName' => 'Contact'], $body['voucher']['supplier']);
        $this->assertSame('ER-1', $body['voucher']['description']);
        $this->assertSame('01.06.2026', $body['voucher']['voucherDate']);
        $this->assertSame('abc123.xml', $body['filename']);
        $this->assertEquals([[
            'objectName' => 'VoucherPos', 'mapAll' => true, 'accountDatev' => ['id' => 5400, 'objectName' => 'AccountDatev'],
            'taxRate' => 19, 'net' => false, 'sumGross' => 238, 'comment' => 'ER-1',
        ]], $body['voucherPosSave']);
        $this->assertCount(1, $this->uploads);
        $this->assertStringContainsString('name="file"', $this->uploads[0]);
        $this->assertStringContainsString('.xml"', $this->uploads[0]);

        $mirror = AccountingVoucher::query()->where('plugin_id', SevDeskPlugin::ID)->sole();
        $this->assertSame('5501', $mirror->external_id);
        $this->assertSame($supplier->id, $mirror->supplier_id);
        $this->assertSame(DocumentDirection::Incoming->value, $mirror->direction);
        $this->assertSame(AccountingVoucherState::Draft, $mirror->voucher_state);
        $this->assertSame('238.00', $mirror->total_amount);
        $this->assertNotNull($incoming->refresh()->transferred_at);
    }

    public function test_an_existing_voucher_with_the_same_number_is_only_linked(): void {
        $fake = $this->fakeSevDesk(existing: [$this->listed('ER-2', '238.00')]);
        $this->linkedSupplier();

        $this->receive('ER-2');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Linked, $journal->status);
        $this->assertSame('4401', $journal->external_id);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_an_existing_voucher_with_another_amount_waits(): void {
        $fake = $this->fakeSevDesk(existing: [$this->listed('ER-3', '99.00')]);
        $this->linkedSupplier();

        $this->receive('ER-3');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame('In sevDesk gibt es den Beleg ER-3 schon mit einem anderen Betrag. Bitte dort prüfen und danach die Übergabe erneut anstoßen.', $journal->error);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_without_a_booking_account_the_handover_waits_and_uploads_nothing(): void {
        $this->connect(account: null);
        $fake = $this->fakeSevDesk();
        $this->linkedSupplier();

        $this->receive('ER-4');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame('Für diese Richtung ist in den sevDesk-Einstellungen kein Buchungskonto hinterlegt.', $journal->error);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_bookkeeping_1_0_does_not_take_the_xml_without_a_pdf(): void {
        $fake = $this->fakeSevDesk(version: '1.0');
        $this->linkedSupplier();

        $this->receive('ER-5');

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame('sevDesk nimmt XRechnungen erst ab der Buchhaltungsversion 2.0 an, und dem Eingang liegt kein PDF bei.', $journal->error);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_reverse_charge_is_left_to_accounting(): void {
        $this->connect(transfer: false);
        $this->linkedSupplier();
        $incoming = $this->receive('ER-6');
        $incoming->forceFill(['summary' => ['tax_breakdown' => [['category' => 'AE', 'percent' => 0, 'net' => '200.00', 'tax' => '0.00']]] + (array) $incoming->summary])->save();
        $this->connect();
        $fake = $this->fakeSevDesk();
        $journal = IncomingEInvoiceTransfer::query()->create(['organization_id' => $this->organization->id, 'incoming_einvoice_id' => $incoming->id, 'target' => SevDeskPlugin::ID]);

        $result = app(SevDeskIncomingInvoiceTarget::class)->transfer($incoming->refresh(), $journal);

        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $result->status);
        $this->assertStringStartsWith('Reverse Charge, innergemeinschaftliche Fälle und Ausfuhr bucht die Buchhaltung in sevDesk selbst.', (string) $result->note);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
    }

    public function test_an_unlinked_supplier_is_linked_by_a_unique_name_match(): void {
        $fake = $this->fakeSevDesk(contacts: [['id' => '7002', 'name' => 'Lieferant GmbH'], ['id' => '7003', 'name' => 'Lieferant GmbH & Co. KG']]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lieferant GmbH', 'vat_id' => self::SELLER_VAT]);

        $this->receive('ER-7');

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, IncomingEInvoiceTransfer::query()->sole()->status);
        $this->assertSame('7002', ExternalReference::query()->forPlugin($this->organization->id, SevDeskPlugin::ID, ExternalReference::TYPE_CONTACT)->forReferenceable($supplier)->value('external_id'));
        $this->assertSame(['id' => 7002, 'objectName' => 'Contact'], $this->saveBody($fake)['voucher']['supplier']);
    }

    public function test_the_collective_supplier_goes_without_contact_but_with_the_real_name(): void {
        $fake = $this->fakeSevDesk(existing: [$this->listed('ER-8', '238.00', 'Ganz andere GmbH')]);
        $incoming = $this->receive('ER-8');
        $this->assertNull($incoming->supplier_id);

        app(IncomingInvoiceMatcher::class)->assign($incoming, app(CollectiveContacts::class)->supplier($this->organization), IncomingInvoiceMatchKind::Manual, $this->admin);

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, IncomingEInvoiceTransfer::query()->sole()->status);
        $voucher = $this->saveBody($fake)['voucher'];
        $this->assertNull($voucher['supplier']);
        $this->assertSame('Lieferant GmbH', $voucher['supplierName']);
        $fake->assertNotSent(static fn (RequestInterface $r): bool => str_contains(urldecode($r->getUri()->getQuery()), 'contact[id]'));
    }

    public function test_the_switch_keeps_the_target_off(): void {
        $this->connect(transfer: false);
        $fake = $this->fakeSevDesk();
        $this->linkedSupplier();

        $this->receive('ER-9');

        $this->assertSame(0, IncomingEInvoiceTransfer::query()->count());
        $fake->assertNothingSent();
    }

    private function connect(bool $transfer = true, ?string $account = '5400'): void {
        Cache::forget('sevdesk:bookkeeping_version:' . $this->organization->id);
        PluginSetting::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID],
            ['enabled' => true, 'settings' => array_filter([
                'api_key' => 'tok-123', 'incoming_transfer' => $transfer, 'incoming_account_id' => $account, 'outgoing_account_id' => '8400',
            ], static fn (mixed $value): bool => $value !== null)],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $contacts
     */
    private function fakeSevDesk(array $existing = [], array $contacts = [], string $version = '2.0'): FakePluginHttp {
        return FakePluginHttp::fake([
            self::API . '/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => $version]]),
            self::API . '/Voucher/Factory/uploadTempFile' => function (RequestInterface $request): Psr7Response {
                $this->uploads[] = (string) $request->getBody();

                return FakePluginHttp::response(['objects' => ['filename' => 'abc123.xml', 'mimeType' => 'application/xml']], 201);
            },
            self::API . '/Voucher/Factory/saveVoucher' => FakePluginHttp::response(['objects' => ['voucher' => ['id' => '5501', 'description' => 'ER-1', 'status' => '50']]], 201),
            self::API . '/Voucher?*' => FakePluginHttp::response(['objects' => $existing]),
            self::API . '/Contact?*' => FakePluginHttp::response(['objects' => $contacts]),
        ]);
    }

    /** @return array<string, mixed> */
    private function listed(string $number, string $gross, string $supplierName = 'Lieferant GmbH'): array {
        return [
            'id' => '4401', 'objectName' => 'Voucher', 'description' => $number, 'voucherDate' => '2026-06-01T00:00:00+02:00',
            'status' => '100', 'creditDebit' => 'C', 'sumGross' => $gross, 'supplierName' => $supplierName,
            'supplier' => ['id' => '7001', 'objectName' => 'Contact', 'name' => $supplierName],
        ];
    }

    /** @return array<string, mixed> */
    private function saveBody(FakePluginHttp $fake): array {
        $body = null;
        foreach ($fake->recorded() as $call) {
            if ($call['request']->getMethod() === 'POST' && str_ends_with($call['request']->getUri()->getPath(), '/Voucher/Factory/saveVoucher')) {
                $body = (string) $call['request']->getBody();
            }
        }
        $this->assertNotNull($body);

        return (array) json_decode($body, true);
    }

    private function linkedSupplier(): Supplier {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lieferant GmbH', 'vat_id' => self::SELLER_VAT]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $supplier->getMorphClass(), 'referenceable_id' => $supplier->id, 'external_id' => '7001', 'synced_at' => now(),
        ]);

        return $supplier;
    }

    private function receive(string $number): IncomingEInvoice {
        $customer = Customer::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH'], [
            'currency' => 'EUR', 'email' => 'einkauf@muster.example', 'address_street' => 'Kundenweg 7',
            'address_zip' => '54321', 'address_city' => 'Hamburg', 'country' => 'DE', 'buyer_reference' => '991-12345-73',
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

        $incoming = app(IncomingEInvoiceService::class)->storeIncoming($this->admin, $this->incomingXml($invoice), 'application/xml', source: 'mail')['incoming'];
        $this->assertNotNull($incoming);

        return $incoming->refresh();
    }
}
