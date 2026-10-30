<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNameTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\PluginSetting;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\{LexofficeContactSync, LexofficeMapper};
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Feature\Plugins\OrgaMax\OrgaMaxApiResponses;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Phase 142 (MVP-1138): Anrede, Titel und Namensteile des Ansprechpartners
 * gehen an Lexoffice, easybill und orgaMAX; der Lexoffice-Abgleich bringt sie
 * zurück und füllt den leeren Hauptansprechpartner (primär, sonst der erste;
 * Privatperson selbst).
 */
class ContactNameTransferTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    private function company(): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'company' => 'ACME GmbH',
            'number' => 'K-1001',
            'number_source' => 'local',
            'email' => 'info@acme.test',
            'phone' => null,
            'mobile' => null,
            'contact_name' => null,
            'contact_salutation' => ContactSalutation::Ms,
            'contact_title' => 'Dr.',
            'contact_first_name' => 'Anna',
            'contact_middle_names' => 'Maria',
            'contact_last_name' => 'Müller',
        ]);
    }

    /** @return array<string, mixed> */
    private static function body(RequestInterface $request): array {
        return (array) json_decode((string) $request->getBody(), true);
    }

    public function test_lexoffice_contact_person_gets_salutation_and_first_names(): void {
        $payload = app(LexofficeMapper::class)->customerToContactPayload($this->company());

        $this->assertSame([
            'salutation' => 'Frau Dr.',
            'firstName' => 'Anna Maria',
            'lastName' => 'Müller',
            'emailAddress' => 'info@acme.test',
            'primary' => true,
        ], $payload['company']['contactPersons'][0]);
    }

    public function test_lexoffice_private_customer_is_split_by_the_name_helper(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Herr Hans Peter von Berg',
            'company' => null,
            'vat_id' => null,
        ]);

        $payload = app(LexofficeMapper::class)->customerToContactPayload($customer);

        $this->assertSame(['salutation' => 'Herr', 'firstName' => 'Hans Peter', 'lastName' => 'von Berg'], $payload['person']);
    }

    public function test_lexoffice_sync_brings_salutation_and_parts_back(): void {
        $customer = $this->company();
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-1',
        ]);
        FakePluginHttp::fake(['https://api.lexware.io/v1/contacts*' => FakePluginHttp::response(['content' => [[
            'id' => 'lex-1',
            'company' => ['name' => 'ACME GmbH', 'contactPersons' => [
                ['salutation' => 'Herr Prof.', 'firstName' => 'Karl Heinz', 'lastName' => 'Berg', 'emailAddress' => 'k@acme.test', 'primary' => true],
            ]],
        ]], 'totalPages' => 1])]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $person = $customer->fresh()?->contact_persons[0] ?? [];
        $this->assertSame('mr', $person['salutation'] ?? null);
        $this->assertSame('Prof.', $person['title'] ?? null);
        $this->assertSame('Karl', $person['first_name'] ?? null);
        $this->assertSame('Heinz', $person['middle_names'] ?? null);
        $this->assertSame('Berg', $person['last_name'] ?? null);
        $this->assertSame('Prof. Karl Heinz Berg', $person['name'] ?? null);
    }

    public function test_lexoffice_sync_fills_the_empty_main_contact_from_the_primary_person(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stimmgerecht oHG', 'company' => 'Stimmgerecht oHG', 'contact_name' => null]);
        $this->link($customer, 'lex-2');
        $this->fakeContacts([[
            'id' => 'lex-2',
            'company' => ['name' => 'Stimmgerecht oHG', 'contactPersons' => [
                ['salutation' => 'Herr', 'firstName' => 'Ralph', 'lastName' => 'Mustermann', 'emailAddress' => 'r@stimmgerecht.test'],
                ['salutation' => 'Frau', 'firstName' => 'Birgit', 'lastName' => 'Mustermann', 'emailAddress' => 'b@stimmgerecht.test', 'primary' => true],
            ]],
        ]]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $customer->refresh();
        $this->assertSame([ContactSalutation::Ms, 'Birgit', 'Mustermann', 'Birgit Mustermann'], [$customer->contact_salutation, $customer->contact_first_name, $customer->contact_last_name, $customer->contact_name]);
        $this->assertCount(2, $customer->contact_persons ?? []);
    }

    public function test_lexoffice_sync_takes_the_first_person_without_a_primary_flag_and_keeps_a_maintained_main_contact(): void {
        $empty = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Leer GmbH', 'company' => 'Leer GmbH', 'contact_name' => null]);
        $maintained = $this->company();
        $this->link($empty, 'lex-3');
        $this->link($maintained, 'lex-4');
        $persons = [
            ['salutation' => 'Herr', 'firstName' => 'Ralph', 'lastName' => 'Mustermann'],
            ['salutation' => 'Frau', 'firstName' => 'Birgit', 'lastName' => 'Mustermann'],
        ];
        $this->fakeContacts([
            ['id' => 'lex-3', 'company' => ['name' => 'Leer GmbH', 'contactPersons' => $persons]],
            ['id' => 'lex-4', 'company' => ['name' => 'ACME GmbH', 'contactPersons' => $persons]],
        ]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $this->assertSame(['Ralph', 'Mustermann'], [$empty->refresh()->contact_first_name, $empty->contact_last_name]);
        $this->assertSame(['Anna', 'Müller'], [$maintained->refresh()->contact_first_name, $maintained->contact_last_name]);
        $this->assertCount(2, $maintained->contact_persons ?? []);
    }

    public function test_lexoffice_private_person_becomes_the_main_contact_on_creation(): void {
        $this->fakeContacts([[
            'id' => 'lex-5',
            'person' => ['salutation' => 'Frau', 'firstName' => 'Maria Luise', 'lastName' => 'Mustermann'],
            'emailAddresses' => ['business' => ['maria@mustermann.test']],
        ]]);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins, createMissingLocal: true);

        $customer = Customer::query()->where('name', 'Maria Luise Mustermann')->sole();
        $this->assertNull($customer->company);
        $this->assertSame([ContactSalutation::Ms, 'Maria', 'Luise', 'Mustermann'], [$customer->contact_salutation, $customer->contact_first_name, $customer->contact_middle_names, $customer->contact_last_name]);
    }

    private function link(Customer $customer, string $externalId): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function fakeContacts(array $items): void {
        FakePluginHttp::fake(['https://api.lexware.io/v1/contacts*' => FakePluginHttp::response(['content' => $items, 'totalPages' => 1])]);
    }

    public function test_easybill_customer_gets_the_contact_name_fields(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        $customer = $this->company();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['items' => []]),
            'https://api.easybill.de/rest/v1/customers' => FakePluginHttp::response(['id' => 501, 'number' => 'K-1001'], 201),
        ]);

        app(EasybillPlugin::class)->pushContact($customer);

        $fake->assertSent(function (RequestInterface $r): bool {
            $body = self::body($r);

            return $r->getMethod() === 'POST'
                && ($body['salutation'] ?? null) === 2
                && ($body['title'] ?? null) === 'Dr.'
                && ($body['first_name'] ?? null) === 'Anna Maria'
                && ($body['last_name'] ?? null) === 'Müller';
        });
    }

    public function test_orgamax_creates_the_main_contact_person_only_on_creation(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active,
        ]);
        $customer = $this->company();
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer?*' => self::listResponse([]),
            'https://api.orgamax.de/openapi/customer/' => self::itemResponse(['id' => 'om-7', 'number' => 'K-1001'], 201),
            'https://api.orgamax.de/openapi/customer/om-7' => fn () => self::itemResponse(['id' => 'om-7']),
        ]);

        app(OrgaMaxPlugin::class)->pushContact($customer);
        $created = null;
        foreach ($fake->recorded() as $entry) {
            if ($entry['request']->getMethod() === 'POST') {
                $created = self::body($entry['request']);
            }
        }
        $person = $created['contactPersons'][0] ?? [];
        $this->assertSame('Frau', $person['salutation'] ?? null);
        $this->assertSame('Dr.', $person['title'] ?? null);
        $this->assertSame('Anna Maria', $person['firstName'] ?? null);
        $this->assertSame('Müller', $person['lastName'] ?? null);
        $this->assertTrue($person['isMainContact'] ?? null);

        app(OrgaMaxPlugin::class)->pushContact($customer->fresh() ?? $customer);
        // Das SDK serialisiert die leere Liste als `[[null]]`; es darf nur kein Ansprechpartner mitgehen.
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && ! str_contains((string) $r->getBody(), 'isMainContact'));
    }

    public function test_orgamax_renames_through_the_billing_address(): void {
        // Befund B8 (Phase 145): orgaMAX liest den Namen nur aus der Rechnungsanschrift, beide Anschriften gehen mit ihrer ID zurück.
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active,
        ]);
        $customer = $this->company();
        \App\Models\Integration\ExternalReference::create(['organization_id' => $this->organization->id, 'plugin_id' => OrgaMaxPlugin::ID, 'external_type' => \App\Models\Integration\ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => 'om-7']);
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer/om-7' => fn () => self::itemResponse(['id' => 'om-7', 'customerDefaultAddress' => [
                'id' => 'cda-1',
                'billingAddress' => ['id' => 'adr-1', 'companyName' => 'ACME alt', 'street' => 'Hafenweg 1', 'zipCode' => '20457', 'city' => 'Hamburg'],
                'deliveryAddress' => ['id' => 'adr-2', 'companyName' => 'ACME alt', 'street' => 'Lagerstraße 5', 'zipCode' => '20457', 'city' => 'Hamburg'],
            ]]),
        ]);

        app(OrgaMaxPlugin::class)->pushContact($customer);

        $fake->assertSent(function (RequestInterface $request): bool {
            $address = self::body($request)['customerDefaultAddress'] ?? [];

            return $request->getMethod() === 'PUT'
                && ($address['billingAddress']['companyName'] ?? null) === 'ACME GmbH'
                && ($address['billingAddress']['id'] ?? null) === 'adr-1'
                && ($address['billingAddress']['street'] ?? null) === 'Hafenweg 1'
                && ($address['deliveryAddress']['id'] ?? null) === 'adr-2';
        });
    }
}
