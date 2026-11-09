<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPhoneContactSourcesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\PluginSetting;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillPhoneContactSource;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxPhoneContactSource;
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactDirectory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Plugins\OrgaMax\OrgaMaxApiResponses;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Rufnummernquellen easybill und orgaMAX (MVP-1500) für FRITZ!Box und CTI:
 * Telefonfelder laut Swagger-Fixture bzw. SDK-OpenAPI, verknüpfte Treffer
 * buchen auf den lokalen Kunden.
 */
final class AccountingPhoneContactSourcesTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function link(string $pluginId, string $type, string $externalId, Customer $customer): void {
        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_type' => $type,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => $externalId,
        ]);
    }

    public function test_easybill_customers_provide_phone_1_phone_2_and_mobile_and_resolve_the_linked_customer(): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'eb']]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lotsenhaus GbR', 'phone' => null, 'mobile' => null]);
        $this->link(EasybillPlugin::ID, 'contact', '501', $customer);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['page' => 1, 'pages' => 1, 'limit' => 1000, 'total' => 2, 'items' => [
                ['id' => 501, 'company_name' => 'Lotsenhaus GbR', 'phone_1' => '040 3344550', 'phone_2' => '', 'mobile' => '0171 2223334'],
                ['id' => 502, 'company_name' => 'Ohne Nummer AG', 'phone_1' => null],
            ]]),
        ]);

        $contacts = [...(new EasybillPhoneContactSource)->contacts($this->organization)];

        $this->assertCount(1, $contacts, 'Kunden ohne Rufnummer fallen heraus');
        $this->assertInstanceOf(ExternalPhoneContact::class, $contacts[0]);
        $this->assertSame(['040 3344550', '0171 2223334'], $contacts[0]->phones);
        $this->assertSame('GET https://api.easybill.de/rest/v1/customers?page=1&limit=1000', $fake->recorded()[0]['request']->getMethod() . ' ' . $fake->recorded()[0]['request']->getUri());

        $match = (new ExternalPhoneContactDirectory([new EasybillPhoneContactSource]))->find($this->organization, '+491712223334');
        $this->assertNotNull($match);
        $this->assertTrue($match->target?->is($customer), 'verknüpfter easybill-Kontakt bucht auf den Kunden');
        $this->assertSame(['easybill'], $match->sourceLabels);
    }

    public function test_orgamax_customers_and_contact_persons_provide_phones_and_the_import_link_resolves(): void {
        OrgaMaxConnection::query()->create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's', 'ownership_id' => 'own-1',
            'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(), 'status' => OrgaMaxConnectionStatus::Active,
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fischhalle Süd', 'phone' => null, 'mobile' => null]);
        // Der Stammdatenimport verknüpft unter `customer`.
        $this->link(OrgaMaxPlugin::ID, 'customer', 'om-7', $customer);
        FakePluginHttp::fake(['https://api.orgamax.de/openapi/customer?*' => self::listResponse([
            ['id' => 'om-7', 'name' => 'Fischhalle Süd', 'companyName' => 'Fischhalle Süd', 'phone1' => '0421 556677', 'mobile' => '0160 9988776',
                'contactPersons' => [['firstName' => 'Greta', 'lastName' => 'Mustermann', 'phone2' => '0421 556699']]],
            ['id' => 'om-8', 'name' => 'Ohne Telefon KG'],
        ])]);

        $contacts = [...(new OrgaMaxPhoneContactSource)->contacts($this->organization)];

        $this->assertCount(1, $contacts);
        $this->assertSame(['0421 556677', '0160 9988776', '0421 556699'], $contacts[0]->phones);
        $this->assertSame('Fischhalle Süd', $contacts[0]->name);

        $match = (new ExternalPhoneContactDirectory([new OrgaMaxPhoneContactSource]))->find($this->organization, '0421556699');
        $this->assertTrue($match?->target?->is($customer), 'Ansprechpartner-Nummer bucht über die Importverknüpfung auf den Kunden');
    }

    public function test_sources_are_unavailable_without_access(): void {
        $this->assertFalse((new EasybillPhoneContactSource)->isAvailable($this->organization));
        $this->assertFalse((new OrgaMaxPhoneContactSource)->isAvailable($this->organization));
        $this->assertSame([], (new OrgaMaxPhoneContactSource)->contacts($this->organization));

        $tagged = array_map(static fn (object $source): string => $source::class, [...app()->tagged('external-phone-contact-sources')]);
        $this->assertContains(EasybillPhoneContactSource::class, $tagged);
        $this->assertContains(OrgaMaxPhoneContactSource::class, $tagged);
    }
}
