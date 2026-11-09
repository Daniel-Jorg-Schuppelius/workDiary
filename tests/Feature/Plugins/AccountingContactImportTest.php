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
 */
final class AccountingContactImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string SEVDESK_CONTACTS = 'https://my.sevdesk.de/api/v1/Contact*';

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
        FakePluginHttp::fake([self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 5, 'objects' => [
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
        FakePluginHttp::fake([self::SEVDESK_CONTACTS => FakePluginHttp::response(['total' => 0, 'objects' => []])]);

        $this->artisan('sevdesk:pull-contacts', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('0 gelesen, 0 verknüpft')
            ->assertSuccessful();
        $this->artisan('easybill:pull-contacts', ['--organization' => $this->organization->id])->assertSuccessful();
    }
}
