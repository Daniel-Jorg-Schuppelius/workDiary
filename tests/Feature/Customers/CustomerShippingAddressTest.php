<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerShippingAddressTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Contacts\ContactAddress;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Customer\Import\CustomerSpec;
use App\Services\Customer\Mcp\{CreateCustomerTool, CustomersTool};
use App\Services\Export\Specs\CustomerExportSpec;
use App\Services\Import\ImportOutcome;
use App\Services\Mcp\WorkDiaryMcpServer;
use App\Services\Privacy\SubjectData\CustomerMasterDataSection;
use App\Services\Stammdaten\{ContactDetailsWriter, CustomerMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1689/1694: abweichende Lieferanschrift am Kunden als Satellit
 * (`contact_addresses`, Art `shipping`). Die Projektion `address_*` bleibt die
 * Rechnungsanschrift; Dialog, Detailseite, Zusammenführen, Auskunft, CSV, API
 * und MCP führen die Lieferanschrift, die Registernummer auch API und MCP.
 */
final class CustomerShippingAddressTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var array<string, string> */
    private const array SHIPPING = [
        'shipping_street' => 'Lagerweg 9',
        'shipping_supplement' => 'Tor 3',
        'shipping_zip' => '4020',
        'shipping_city' => 'Linz',
        'shipping_country' => 'at',
    ];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_dialog_writes_the_shipping_address_and_the_projection_stays_the_billing_address(): void {
        $this->actingAs($this->admin)->post(route('customers.store'), $this->form() + self::SHIPPING)->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'Lieferkunde GmbH')->firstOrFail();
        $this->assertSame('Rechnungsweg 1', $customer->address_street);
        $this->assertSame('Musterstadt', $customer->address_city);
        $this->assertSame('DE', $customer->country);
        $this->assertSame(ContactAddress::KIND_BILLING, $customer->primaryAddress()?->kind);
        $this->assertSame([
            'shipping_street' => 'Lagerweg 9',
            'shipping_supplement' => 'Tor 3',
            'shipping_zip' => '4020',
            'shipping_city' => 'Linz',
            'shipping_country' => 'AT',
        ], $customer->shippingAddressFields());

        // Nur die Lieferanschrift ändert sich: die Projektion bleibt die Rechnungsanschrift.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), ['shipping_city' => 'Graz'] + $this->form() + self::SHIPPING)
            ->assertSessionHasNoErrors();
        $customer->refresh();
        $this->assertSame('Graz', $customer->shippingAddress()?->city);
        $this->assertSame('Musterstadt', $customer->address_city);
        $this->assertSame(1, $customer->addresses()->where('kind', ContactAddress::KIND_SHIPPING)->count());

        // Alles leer = keine Lieferanschrift.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), $this->form() + array_fill_keys(array_keys(self::SHIPPING), ''))
            ->assertSessionHasNoErrors();
        $this->assertNull($customer->refresh()->shippingAddress());
        $this->assertSame('Rechnungsweg 1', $customer->address_street);
    }

    /** Gegenprobe zur Projektion: Kunde ohne Rechnungs-Satellit, nur mit Lieferanschrift. */
    public function test_a_shipping_address_never_overwrites_the_projection(): void {
        $customer = $this->customer(['address_street' => 'Inlineweg 2', 'address_zip' => '12345', 'address_city' => 'Altstadt', 'country' => 'DE']);

        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $customer->refresh();
        $this->assertSame('Inlineweg 2', $customer->address_street);
        $this->assertSame('Altstadt', $customer->address_city);
        $this->assertSame('DE', $customer->country);
        $this->assertNull($customer->primaryAddress(), 'Die Lieferanschrift ist nie die primäre Anschrift.');
        $this->assertSame(['Inlineweg 2', '12345 Altstadt'], $customer->postalAddressLines());

        // Die Rechnungsanschrift schreibt weiter in ihren eigenen Satelliten.
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Neuweg 3', 'zip' => '12345', 'city' => 'Altstadt', 'country' => 'DE']);
        $this->assertSame('Neuweg 3', $customer->refresh()->address_street);
        $this->assertSame('Lagerweg 9', $customer->shippingAddress()?->street);
        $this->assertSame(2, $customer->addresses()->count());
    }

    /** Gegenprobe: Ein Formular ohne Lieferanschrift-Felder (Lieferant) lässt eine vorhandene stehen. */
    public function test_a_form_without_shipping_fields_keeps_the_shipping_address(): void {
        $supplier = Supplier::query()->create(['organization_id' => $this->organization->id, 'name' => 'Teile GmbH']);
        app(ContactDetailsWriter::class)->writeShippingAddress($supplier, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), ['name' => 'Teile GmbH', 'currency' => 'EUR', 'address_city' => 'Musterstadt'])
            ->assertRedirect();

        $this->assertSame('Linz', $supplier->refresh()->shippingAddressFields()['shipping_city']);
        $this->assertSame('Musterstadt', $supplier->address_city);
    }

    public function test_edit_dialog_and_detail_page_show_the_shipping_address(): void {
        $customer = $this->customer();
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'supplement' => 'Tor 3', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $this->actingAs($this->admin)->get(route('customers.edit', [$customer, 'dialog' => 1]))
            ->assertOk()
            ->assertSee('name="shipping_street"', false)
            ->assertSee('value="Lagerweg 9"', false)
            ->assertSee('value="Tor 3"', false);

        $this->actingAs($this->admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('stammdaten.shipping.heading'))
            ->assertSee('4020 Linz');
    }

    public function test_merge_keeps_one_shipping_address(): void {
        $target = $this->customer(['name' => 'Ziel']);
        $source = $this->customer(['name' => 'Quelle']);
        app(ContactDetailsWriter::class)->writeShippingAddress($source, ['street' => 'Quellweg 1', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        // Ziel ohne Lieferanschrift übernimmt die der Quelle.
        app(CustomerMergeService::class)->merge($source, $target);
        $this->assertSame('Quellweg 1', $target->refresh()->shippingAddress()?->street);

        // Ziel mit eigener behält sie; ausgewählt ersetzt die der Quelle.
        $second = $this->customer(['name' => 'Zweite Quelle']);
        app(ContactDetailsWriter::class)->writeShippingAddress($second, ['street' => 'Zweitweg 2', 'zip' => '8010', 'city' => 'Graz', 'country' => 'AT']);
        app(CustomerMergeService::class)->merge($second, $target);
        $this->assertSame('Quellweg 1', $target->refresh()->shippingAddress()?->street);
        $this->assertSame(1, $target->addresses()->where('kind', ContactAddress::KIND_SHIPPING)->count());

        $third = $this->customer(['name' => 'Dritte Quelle']);
        app(ContactDetailsWriter::class)->writeShippingAddress($third, ['street' => 'Drittweg 3', 'zip' => '5020', 'city' => 'Salzburg', 'country' => 'AT']);
        $this->actingAs($this->admin)->get(route('customers.duplicates.compare', ['source' => $third->sqid, 'target' => $target->sqid]))
            ->assertOk()
            ->assertSee('Drittweg 3, 5020 Salzburg, AT')
            ->assertSee('value="shipping_address"', false);
        $this->actingAs($this->admin)->post(route('customers.duplicates.merge'), ['source' => $third->sqid, 'target' => $target->sqid, 'prefer_source' => ['shipping_address']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Drittweg 3', $target->refresh()->shippingAddressFields()['shipping_street']);
        $this->assertSame(1, $target->addresses()->where('kind', ContactAddress::KIND_SHIPPING)->count());
    }

    public function test_subject_access_export_and_hard_delete_cover_the_shipping_address(): void {
        $customer = $this->customer();
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $section = app(CustomerMasterDataSection::class)->build($customer);
        $this->assertSame('Lagerweg 9, 4020 Linz, AT', data_get($section, 'fields.shipping_address.value'));

        $row = app(CustomerExportSpec::class)->toRow($customer);
        $this->assertSame(['Lagerweg 9', '', '4020', 'Linz', 'AT'], [$row['shipping_street'], $row['shipping_supplement'], $row['shipping_zip'], $row['shipping_city'], $row['shipping_country']]);

        $customer->delete();
        $this->assertSame(0, ContactAddress::query()->withoutGlobalScopes()->where('addressable_type', $customer->getMorphClass())->where('addressable_id', $customer->id)->count());
    }

    public function test_csv_import_writes_the_shipping_address_as_satellite(): void {
        $spec = new CustomerSpec();
        $row = $spec->normalize(['name' => 'Importkunde', 'number' => 'K-IMP-1', 'address_street' => 'Rechnungsweg 1', 'address_city' => 'Musterstadt', 'currency' => 'EUR'] + self::SHIPPING);
        $this->assertSame([], $spec->validateRow($row, $this->organization));

        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $customer = Customer::query()->where('number', 'K-IMP-1')->firstOrFail();
        $this->assertSame(['Linz', 'AT'], array_values(array_intersect_key($customer->shippingAddressFields(), array_flip(['shipping_city', 'shipping_country']))));
        $this->assertSame('Rechnungsweg 1', $customer->address_street);

        // Erneuter Import ändert nur die gelieferten Spalten.
        [$again] = $spec->upsert($spec->normalize(['name' => 'Importkunde', 'number' => 'K-IMP-1', 'shipping_city' => 'Wels']), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $again);
        $this->assertSame(['Lagerweg 9', 'Wels'], array_values(array_intersect_key($customer->shippingAddressFields(), array_flip(['shipping_street', 'shipping_city']))));

        $invalid = $spec->normalize(['name' => 'Falsches Land', 'shipping_country' => 'XX']);
        $this->assertNotSame([], $spec->validateRow($invalid, $this->organization));
    }

    /** MVP-1694: Registernummer und Lieferanschrift über die Kunden-API. */
    public function test_api_reads_and_writes_register_number_and_shipping_address(): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(route('api.customers.store'), ['name' => 'API-Kunde', 'currency' => 'EUR', 'register_number' => 'HRB 12345, Amtsgericht Musterstadt'] + self::SHIPPING)
            ->assertCreated()
            ->assertJsonPath('data.register_number', 'HRB 12345, Amtsgericht Musterstadt')
            ->assertJsonPath('data.shipping_city', 'Linz')
            ->assertJsonPath('data.shipping_country', 'AT');
        $customer = Customer::query()->where('name', 'API-Kunde')->firstOrFail();

        // PUT ohne Lieferanschrift lässt sie stehen; gesendete Felder ändern nur sich.
        $this->putJson(route('api.customers.update', $customer), ['name' => 'API-Kunde', 'currency' => 'EUR', 'register_number' => 'HRB 999'])
            ->assertOk()
            ->assertJsonPath('data.register_number', 'HRB 999')
            ->assertJsonPath('data.shipping_street', 'Lagerweg 9');
        $this->putJson(route('api.customers.update', $customer), ['name' => 'API-Kunde', 'currency' => 'EUR', 'shipping_city' => 'Wels'])
            ->assertOk()
            ->assertJsonPath('data.shipping_city', 'Wels')
            ->assertJsonPath('data.shipping_street', 'Lagerweg 9');
        $this->assertNull($customer->refresh()->address_street, 'Die Lieferanschrift füllt die Projektion nicht.');

        $this->putJson(route('api.customers.update', $customer), ['name' => 'API-Kunde', 'currency' => 'EUR'] + array_fill_keys(array_keys(self::SHIPPING), null))
            ->assertOk()
            ->assertJsonPath('data.shipping_street', null);
        $this->assertNull($customer->refresh()->shippingAddress());

        $this->postJson(route('api.customers.store'), ['name' => 'Falsch', 'currency' => 'EUR', 'shipping_country' => 'XX'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_country']);
    }

    /** MVP-1694: Das MCP-Werkzeug legt Registernummer und Lieferanschrift an, das Lesewerkzeug zeigt sie. */
    public function test_mcp_creates_and_shows_register_number_and_shipping_address(): void {
        $this->organization->update(['settings' => ['mcp' => ['enabled' => '1']]]);
        $this->admin->unsetRelation('roles')->unsetRelation('permissions');
        Sanctum::actingAs($this->admin, ['mcp:write', 'mcp:read']);

        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'MCP-Kunde', 'register_number' => 'HRB 12345, Amtsgericht Musterstadt', 'address_city' => 'Musterstadt'] + self::SHIPPING)
            ->assertOk()->assertHasNoErrors();

        $customer = Customer::query()->where('name', 'MCP-Kunde')->firstOrFail();
        $this->assertSame('HRB 12345, Amtsgericht Musterstadt', $customer->register_number);
        $this->assertSame('AT', $customer->shippingAddress()?->country_code);
        $this->assertSame('Musterstadt', $customer->refresh()->address_city);

        WorkDiaryMcpServer::tool(CustomersTool::class, ['id' => $customer->sqid])
            ->assertOk()
            ->assertSee('HRB 12345, Amtsgericht Musterstadt')
            ->assertSee('Lagerweg 9');
    }

    /** @return array<string, mixed> */
    private function form(): array {
        return [
            'name' => 'Lieferkunde GmbH',
            'currency' => 'EUR',
            'address_street' => 'Rechnungsweg 1',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'country' => 'DE',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Lieferung',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }
}
