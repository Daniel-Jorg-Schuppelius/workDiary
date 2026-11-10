<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingContactImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\PluginSetting;
use App\Models\Supplier\Supplier;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillContactImportService;
use App\Plugins\SevDesk\Services\SevDeskContactImportService;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Stammdaten\ContactDetailsWriter;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kontakt-Rückweg aus sevDesk und easybill bei „Buchhaltung führt“
 * (MVP-1502): verknüpfte Kontakte übernehmen den Stand des Programms, sichere
 * Treffer werden verknüpft, Unbekanntes wird Klärfall, nichts wird angelegt.
 * sevDesk bringt Anschrift und Kommunikationswege mit (MVP-1529).
 */
final class AccountingContactImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string SEVDESK_CONTACTS = 'https://my.sevdesk.de/api/v1/Contact?*';

    private const string SEVDESK_ADDRESSES = 'https://my.sevdesk.de/api/v1/ContactAddress*';

    private const string SEVDESK_WAYS = 'https://my.sevdesk.de/api/v1/CommunicationWay*';

    private const string EASYBILL_CUSTOMERS = 'https://api.easybill.de/rest/v1/customers*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        foreach ([SevDeskPlugin::ID, EasybillPlugin::ID] as $pluginId) {
            PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        }
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
    }

    private function link(Customer|Supplier $party, string $pluginId, string $externalId): void {
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $party->getMorphClass(), 'referenceable_id' => $party->id, 'external_id' => $externalId,
        ]);
    }

    private function reference(Customer|Supplier $party, string $pluginId): ?ExternalReference {
        return ExternalReference::query()->forPlugin($this->organization->id, $pluginId, ExternalReference::TYPE_CONTACT)->forReferenceable($party)->first();
    }

    public function test_sevdesk_contacts_update_linked_parties_link_safe_matches_and_stage_the_rest(): void {
        $linked = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seeblick Pension', 'vat_id' => null]);
        $this->link($linked, SevDeskPlugin::ID, '42');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Papierhaus Ahornweg', 'vat_id' => 'DE333333331']);
        FakePluginHttp::fake([self::SEVDESK_ADDRESSES => FakePluginHttp::response(['total' => 0, 'objects' => []]), self::SEVDESK_WAYS => FakePluginHttp::response(['total' => 0, 'objects' => []]), self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 5, 'objects' => [
            ['id' => '42', 'objectName' => 'Contact', 'name' => 'Seeblick Pension & Café', 'customerNumber' => '1042', 'vatNumber' => 'DE444444442', 'category' => ['id' => '3', 'objectName' => 'Category']],
            ['id' => '43', 'objectName' => 'Contact', 'name' => 'Papierhaus Ahornweg KG', 'vatNumber' => 'DE333333331', 'category' => ['id' => '2', 'objectName' => 'Category']],
            ['id' => '44', 'objectName' => 'Contact', 'surename' => 'Greta', 'familyname' => 'Sonnenberg', 'customerNumber' => '1044', 'category' => ['id' => '3', 'objectName' => 'Category']],
            ['id' => '45', 'objectName' => 'Contact', 'name' => 'Partnerbüro Nord', 'category' => ['id' => '4', 'objectName' => 'Category']],
            ['id' => '46', 'objectName' => 'Contact', 'surename' => 'Jan', 'familyname' => 'Ostwald', 'parent' => ['id' => '42', 'objectName' => 'Contact'], 'category' => ['id' => '3', 'objectName' => 'Category']],
        ]])]);

        $counters = app(SevDeskContactImportService::class)->run($this->organization);

        $this->assertSame(['read' => 5, 'linked' => 2, 'inboxed' => 1, 'skipped' => 2], $counters);
        $linked->refresh();
        $this->assertSame(['Seeblick Pension & Café', 'DE444444442'], [$linked->name, $linked->vat_id], 'Das Programm führt die Stammdaten.');
        $this->assertSame('1042', $this->reference($linked, SevDeskPlugin::ID)?->external_number);
        $this->assertSame('43', $this->reference($supplier, SevDeskPlugin::ID)?->external_id, 'Lieferant über die USt-IdNr. verknüpft.');
        $this->assertSame('Papierhaus Ahornweg', $supplier->fresh()?->name, 'Ein sicherer Treffer wird nur verknüpft.');

        $item = IntegrationInboxItem::query()->where('plugin_id', SevDeskPlugin::ID)->sole();
        $this->assertSame([IntegrationInboxItem::CASE_UNMATCHED, 'contact', '44', IntegrationInboxStatus::Open], [$item->case_type, $item->external_type, $item->external_id, $item->status]);
        $this->assertSame(1, Customer::query()->count(), 'Nichts wird angelegt.');
    }

    public function test_sevdesk_contacts_bring_the_primary_address_and_communication_ways(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seeblick Pension', 'email' => 'alt@seeblick.test', 'phone' => null, 'fax' => null, 'mobile' => null]);
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Altweg 1', 'zip' => '79100', 'city' => 'Freiburg', 'country' => 'DE']);
        $this->link($customer, SevDeskPlugin::ID, '42');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Papierhaus Ahornweg', 'email' => 'lokal@papierhaus-ahornweg.test', 'phone' => null]);
        app(ContactDetailsWriter::class)->writeAddress($supplier, ['street' => 'Ahornweg 5', 'zip' => '68161', 'city' => 'Mannheim']);
        $this->link($supplier, SevDeskPlugin::ID, '43');
        $details = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/SevDesk/contact-details-list.json')), true);
        $fake = FakePluginHttp::fake([
            self::SEVDESK_ADDRESSES => FakePluginHttp::response($details['addresses']),
            self::SEVDESK_WAYS => FakePluginHttp::response($details['communicationWays']),
            self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 2, 'objects' => [
                ['id' => '42', 'objectName' => 'Contact', 'name' => 'Seeblick Pension', 'customerNumber' => '1042', 'category' => ['id' => '3', 'objectName' => 'Category']],
                ['id' => '43', 'objectName' => 'Contact', 'name' => 'Papierhaus Ahornweg', 'category' => ['id' => '2', 'objectName' => 'Category']],
            ]]),
        ]);

        $this->assertSame(['read' => 2, 'linked' => 2, 'inboxed' => 0, 'skipped' => 0], app(SevDeskContactImportService::class)->run($this->organization));

        $customer->refresh();
        $this->assertSame(['Hafenstraße 12', '79098', 'Freiburg'], [$customer->primaryAddress()?->street, $customer->primaryAddress()?->zip, $customer->primaryAddress()?->city], 'Die Adresse der Push-Kategorie ist die Anschrift, nicht die Lieferadresse.');
        $this->assertSame(['Hafenstraße 12', 'DE'], [$customer->address_street, $customer->country], 'Projektion nachgezogen, Land bleibt lokal.');
        $this->assertSame(1, $customer->addresses()->count());
        $this->assertSame(
            ['buero@seeblick.test', '+49 761 1234560', '+49 761 1234561', '+49 170 1234567', 'https://seeblick.test'],
            [$customer->email, $customer->phone, $customer->fax, $customer->mobile, $customer->homepage],
            'Haupt-E-Mail vor Newsletter-Adresse, Fax und Mobil über den Schlüssel.',
        );

        $supplier->refresh();
        $this->assertSame('Ahornweg 5', $supplier->primaryAddress()?->street, 'Zwei Adressen ohne Push-Kategorie: mehrdeutig, lokal bleibt.');
        $this->assertSame('lokal@papierhaus-ahornweg.test', $supplier->email, 'Zwei E-Mails ohne Hauptkennzeichen: mehrdeutig, lokal bleibt.');
        $this->assertSame('+49 621 7654320', $supplier->phone, 'Die einzige Telefonnummer gilt.');

        foreach (['/ContactAddress', '/CommunicationWay'] as $resource) {
            $calls = array_filter($fake->recorded(), static fn (array $entry): bool => str_ends_with($entry['request']->getUri()->getPath(), $resource));
            $this->assertCount(1, $calls, "{$resource} wird einmal als Liste gelesen, nicht je Kontakt.");
            parse_str(array_values($calls)[0]['request']->getUri()->getQuery(), $query);
            $this->assertSame(['offset' => '0', 'limit' => '1000', 'countAll' => 'true'], $query);
        }
    }

    public function test_nothing_is_read_while_workdiary_leads_the_master_data(): void {
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_WORKDIARY, SettingScope::Organization, $this->organization);
        $fake = FakePluginHttp::fake();

        $this->assertSame(0, app(SevDeskContactImportService::class)->run($this->organization)['read']);
        $this->assertSame(0, app(EasybillContactImportService::class)->run($this->organization)['read']);
        $fake->assertNothingSent();
    }

    public function test_easybill_contacts_carry_customer_and_supplier_roles_and_update_the_address(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werkstatt Kranich', 'vat_id' => 'DE555555553']);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werkstatt Kranich', 'vat_id' => 'DE555555553']);
        $fake = FakePluginHttp::fake([self::EASYBILL_CUSTOMERS => FakePluginHttp::response(['page' => 1, 'pages' => 1, 'limit' => 1000, 'total' => 2, 'items' => [
            ['id' => 9101, 'number' => '10077', 'supplier_number' => '70077', 'company_name' => 'Werkstatt Kranich', 'vat_identifier' => 'DE555555553', 'street' => 'Mühlenweg 4', 'zip_code' => '79100', 'city' => 'Freiburg', 'salutation' => 2, 'first_name' => 'Ida', 'last_name' => 'Kranich', 'emails' => ['ida@kranich.test'], 'archived' => false],
            ['id' => 9102, 'number' => '10078', 'company_name' => 'Altbestand GmbH', 'archived' => true],
        ]])]);

        $counters = app(EasybillContactImportService::class)->run($this->organization);

        $this->assertSame(['read' => 2, 'linked' => 2, 'inboxed' => 0, 'skipped' => 1], $counters);
        $this->assertSame(['9101', '10077'], [$this->reference($customer, EasybillPlugin::ID)?->external_id, $this->reference($customer, EasybillPlugin::ID)?->external_number]);
        $this->assertSame(['9101', '70077'], [$this->reference($supplier, EasybillPlugin::ID)?->external_id, $this->reference($supplier, EasybillPlugin::ID)?->external_number]);
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getMethod() === 'GET' && ($query['limit'] ?? null) === '1000' && ($query['page'] ?? null) === '1';
        });

        // Zweiter Lauf: der verknüpfte Kunde übernimmt die geänderte Anschrift aus easybill.
        FakePluginHttp::fake([self::EASYBILL_CUSTOMERS => FakePluginHttp::response(['page' => 1, 'pages' => 1, 'items' => [
            ['id' => 9101, 'number' => '10077', 'company_name' => 'Werkstatt Kranich', 'vat_identifier' => 'DE555555553', 'street' => 'Hafenstraße 12', 'zip_code' => '79098', 'city' => 'Freiburg'],
        ]])]);
        app(EasybillContactImportService::class)->run($this->organization);
        $customer->refresh();
        $this->assertSame(['Hafenstraße 12', '79098'], [$customer->address_street, $customer->address_zip]);
    }

    public function test_scheduled_command_runs_per_organization(): void {
        $fake = FakePluginHttp::fake([self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 0, 'objects' => []])]);

        $this->artisan('sevdesk:pull-contacts', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('0 gelesen, 0 verknüpft')
            ->assertSuccessful();
        $fake->assertSentCount(1); // ohne Kunden und Lieferanten keine Adressen und Wege
        $this->artisan('easybill:pull-contacts', ['--organization' => $this->organization->id])->assertSuccessful();
    }
}
