<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactSatelliteWritePathsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Stammdaten;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Customer\Import\CustomerSpec;
use App\Services\Import\ImportOutcome;
use App\Services\Integration\{InboxActionService, IntegrationResolver, ResolveOutcome};
use App\Services\Integration\Profiles\CustomerMatchProfile;
use App\Services\Stammdaten\ContactDetailsWriter;
use App\Services\Supplier\Import\SupplierSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1700/1703: Kunden-API und CSV-Import schreiben Rechnungsanschrift und
 * Bankverbindung über den Satelliten. Altbestand ohne Satellit (nur Projektion)
 * bekommt ihn vorher aus der Projektion, ein Teil-Update leert also nichts. Die
 * kompakte Kundenliste führt Registernummer und Lieferanschrift, die Verknüpfung
 * im Integrations-Eingang gleicht die Lieferanschrift ab wie die Neuanlage.
 */
final class ContactSatelliteWritePathsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_api_partial_update_of_a_legacy_customer_keeps_the_projection(): void {
        $customer = $this->legacyCustomer();
        $this->assertNull($customer->primaryAddress(), 'Altbestand: nur Projektion, kein Satellit.');
        $this->assertNull($customer->primaryBankAccount());
        Sanctum::actingAs($this->admin, ['*']);

        $this->putJson(route('api.customers.update', $customer), ['name' => 'Altkunde GmbH', 'currency' => 'EUR', 'address_city' => 'Neustadt'])
            ->assertOk()
            ->assertJsonPath('data.address_street', 'Altweg 1')
            ->assertJsonPath('data.address_city', 'Neustadt')
            ->assertJsonPath('data.country', 'DE');

        $customer->refresh();
        $this->assertSame(['Altweg 1', '12345', 'Neustadt', 'DE'], [$customer->address_street, $customer->address_zip, $customer->address_city, $customer->country]);
        $address = $customer->primaryAddress();
        $this->assertNotNull($address, 'Der Satellit entsteht aus der Projektion.');
        $this->assertSame(['Altweg 1', '12345', 'Neustadt', 'DE'], [$address->street, $address->zip, $address->city, $address->country_code]);
        $this->assertSame(1, $customer->addresses()->count());
        // Ohne gesendete Bankfelder bleibt die Bankverbindung, wie sie war.
        $this->assertSame('DE89370400440532013000', $customer->bank_iban);
        $this->assertNull($customer->primaryBankAccount());

        // Nur die IBAN gesendet: Inhaber und BIC bleiben, aus der Projektion übernommen.
        $this->putJson(route('api.customers.update', $customer), ['name' => 'Altkunde GmbH', 'currency' => 'EUR', 'bank_iban' => 'DE02 1203 0000 0000 2020 51'])
            ->assertOk();
        $customer->refresh();
        $this->assertSame('DE02120300000000202051', $customer->bank_iban);
        $this->assertSame('COBADEFFXXX', $customer->bank_bic);
        $this->assertSame('Alt GmbH', $customer->bank_account_holder);
        $this->assertSame('DE02120300000000202051', $customer->primaryBankAccount()?->iban);
        $this->assertSame('Neustadt', $customer->address_city, 'Die Anschrift bleibt beim Bank-Update unberührt.');
    }

    /** Gegenprobe: Ein vorhandener Satellit gilt; die Projektion wird nicht erneut übernommen. */
    public function test_an_existing_satellite_wins_over_a_diverging_projection(): void {
        $customer = $this->legacyCustomer();
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Satellitenweg 2', 'zip' => '12345', 'city' => 'Altstadt', 'country' => 'DE']);
        DB::table('customers')->where('id', $customer->id)->update(['address_zip' => '99999']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->putJson(route('api.customers.update', $customer), ['name' => 'Altkunde GmbH', 'currency' => 'EUR', 'address_city' => 'Neustadt'])->assertOk();

        $customer->refresh();
        $this->assertSame(['Satellitenweg 2', '12345', 'Neustadt'], [$customer->address_street, $customer->address_zip, $customer->address_city]);
        $this->assertSame(1, $customer->addresses()->count());
    }

    public function test_api_create_writes_address_and_bank_into_the_satellites(): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(route('api.customers.store'), [
            'name' => 'Neukunde GmbH',
            'currency' => 'EUR',
            'address_street' => 'Neuweg 3',
            'address_zip' => '54321',
            'address_city' => 'Neustadt',
            'country' => 'at',
            'bank_iban' => 'DE89 3704 0044 0532 0130 00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.address_city', 'Neustadt')
            ->assertJsonPath('data.country', 'AT');

        $customer = Customer::query()->where('name', 'Neukunde GmbH')->firstOrFail();
        $address = $customer->primaryAddress();
        $this->assertNotNull($address);
        $this->assertSame(['Neuweg 3', 'AT'], [$address->street, $address->country_code]);
        $this->assertSame('DE89370400440532013000', $customer->primaryBankAccount()?->iban);
        $this->assertSame('DE89370400440532013000', $customer->bank_iban);
    }

    public function test_csv_import_updates_a_legacy_customer_without_emptying_the_address(): void {
        $customer = $this->legacyCustomer(['number' => 'K-ALT-1']);
        $spec = new CustomerSpec();

        [$outcome] = $spec->upsert($spec->normalize(['name' => 'Altkunde GmbH', 'number' => 'K-ALT-1', 'address_zip' => '54321']), $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $customer->refresh();
        $this->assertSame(['Altweg 1', '54321', 'Altstadt', 'DE'], [$customer->address_street, $customer->address_zip, $customer->address_city, $customer->country]);
        $this->assertSame('54321', $customer->primaryAddress()?->zip);

        // Neuanlage über den Import: Anschrift im Satelliten, Projektion gleich.
        [$created] = $spec->upsert($spec->normalize(['name' => 'Importkunde', 'number' => 'K-NEU-1', 'address_street' => 'Importweg 5', 'address_city' => 'Musterstadt', 'country' => 'DE']), $this->organization);
        $this->assertSame(ImportOutcome::Created, $created);
        $new = Customer::query()->where('number', 'K-NEU-1')->firstOrFail();
        $this->assertSame('Importweg 5', $new->primaryAddress()?->street);
        $this->assertSame('Importweg 5', $new->address_street);
    }

    public function test_csv_import_updates_a_legacy_supplier_bank_account_in_part(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Teile GmbH',
            'number' => 'L-ALT-1',
            'bank_account_holder' => 'Teile GmbH',
            'bank_iban' => 'DE89370400440532013000',
            'bank_bic' => 'COBADEFFXXX',
        ]);
        $this->assertNull($supplier->primaryBankAccount());
        $street = $supplier->address_street;
        $spec = new SupplierSpec();

        [$outcome] = $spec->upsert($spec->normalize(['name' => 'Teile GmbH', 'number' => 'L-ALT-1', 'bank_name' => 'Musterbank']), $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $supplier->refresh();
        $this->assertSame(['Teile GmbH', 'DE89370400440532013000', 'COBADEFFXXX', 'Musterbank'], [$supplier->bank_account_holder, $supplier->bank_iban, $supplier->bank_bic, $supplier->bank_name]);
        $this->assertSame('Musterbank', $supplier->primaryBankAccount()?->bank_name);
        $this->assertSame($street, $supplier->address_street);
    }

    /** MVP-1703: kompakte Liste mit Registernummer und Lieferanschrift, Kopfzeilen wie die Import-Aliase. */
    public function test_compact_export_lists_register_number_and_shipping_address(): void {
        $customer = $this->legacyCustomer(['register_number' => 'HRB 4711']);
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'supplement' => 'Tor 3', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $body = (string) $this->actingAs($this->admin)->get(route('customers.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('USt-IdNr.;Registernummer;E-Mail', $body);
        $this->assertStringContainsString('Land;Lieferstraße;Lieferadresszusatz;Liefer-PLZ;Lieferort;Lieferland;Währung', $body);
        $this->assertStringContainsString('HRB 4711', $body);
        $this->assertStringContainsString('DE;Lagerweg 9;Tor 3;4020;Linz;AT;EUR', $body);

        // Wieder einlesbar: jede neue Kopfzeile ist ein Alias des Imports.
        $aliases = (new CustomerSpec())->headerAliases();
        foreach (['Registernummer' => 'register_number', 'Lieferstraße' => 'shipping_street', 'Lieferadresszusatz' => 'shipping_supplement', 'Liefer-PLZ' => 'shipping_zip', 'Lieferort' => 'shipping_city', 'Lieferland' => 'shipping_country'] as $header => $column) {
            $this->assertSame($column, $aliases[mb_strtolower($header)] ?? null, $header);
        }
    }

    /** MVP-1703: Die Verknüpfung mit einem vorhandenen Kunden gleicht die Lieferanschrift ab. */
    public function test_linked_record_compares_and_takes_over_the_shipping_address(): void {
        $customer = $this->legacyCustomer();
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);
        $this->reference($customer, 'lx-ship-1');

        $attributes = ['name' => 'Altkunde GmbH', 'shipping_city' => 'Wels', 'shipping_country' => 'at'];
        $outcome = $this->resolve('lx-ship-1', $attributes, ConflictFieldPolicy::ManualReview);

        $this->assertSame(ResolveOutcome::CONFLICT, $outcome->type);
        $item = $outcome->inboxItem;
        $this->assertNotNull($item);
        $this->assertSame(['shipping_city'], $item->diff_fields, 'Das Land ist gleich, nur in anderer Schreibweise.');
        $this->assertSame('Linz', $item->local_snapshot['shipping_city'] ?? null);
        $this->assertSame('Linz', $customer->refresh()->shippingAddress()?->city, 'Manuelle Prüfung ändert nichts.');

        app(InboxActionService::class)->acceptRemote($item);

        $customer->refresh();
        $shipping = $customer->shippingAddress();
        $this->assertNotNull($shipping);
        $this->assertSame(['Wels', 'Lagerweg 9'], [$shipping->city, $shipping->street]);
        $this->assertSame('Altweg 1', $customer->address_street, 'Die Rechnungsanschrift bleibt.');
    }

    public function test_remote_wins_writes_the_shipping_address_and_equal_values_are_no_conflict(): void {
        $customer = $this->legacyCustomer();
        $this->reference($customer, 'lx-ship-2');

        $equal = $this->resolve('lx-ship-2', ['name' => 'Altkunde GmbH', 'address_city' => 'Altstadt'], ConflictFieldPolicy::ManualReview);
        $this->assertSame(ResolveOutcome::LINKED, $equal->type, 'Gegenprobe: ohne Abweichung kein Konflikt.');

        $this->resolve('lx-ship-2', ['name' => 'Altkunde GmbH', 'shipping_street' => 'Neuweg 1', 'shipping_city' => 'Graz', 'shipping_country' => 'AT'], ConflictFieldPolicy::RemoteWins);

        $customer->refresh();
        $this->assertSame(['Neuweg 1', 'Graz', 'AT'], [$customer->shippingAddress()?->street, $customer->shippingAddress()?->city, $customer->shippingAddress()?->country_code]);
        $this->assertSame('Altweg 1', $customer->address_street);
    }

    /** @param array<string, mixed> $attributes */
    private function legacyCustomer(array $attributes = []): Customer {
        return Customer::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Altkunde GmbH',
            'address_street' => 'Altweg 1',
            'address_zip' => '12345',
            'address_city' => 'Altstadt',
            'country' => 'DE',
            'bank_account_holder' => 'Alt GmbH',
            'bank_iban' => 'DE89370400440532013000',
            'bank_bic' => 'COBADEFFXXX',
            'vat_id' => null,
            'email' => null,
        ]);
    }

    private function reference(Customer $customer, string $externalId): void {
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'external_type' => 'contact',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function resolve(string $externalId, array $attributes, ConflictFieldPolicy $onConflict): ResolveOutcome {
        return app(IntegrationResolver::class)->resolve(
            $this->organization, 'lexoffice', app(CustomerMatchProfile::class), 'contact', $externalId,
            $attributes, $attributes, ImportMatchPolicy::AutoLinkExactOnly, $onConflict, 'api',
        );
    }
}
