<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPushIdentityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Numbering\NumberScope;
use App\Exceptions\ContactNumberTakenException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\PluginSetting;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\NumberSequenceService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Plugins\OrgaMax\OrgaMaxApiResponses;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Phase 141 (MVP-1133/1131): Übertragen in die Buchhaltung trifft nur den
 * eigenen Kontakt — verknüpfte über ihre ID, Treffer nur bei gleicher
 * Identität — und übernimmt eine vom Programm vergebene Nummer.
 */
class ContactPushIdentityTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    private const LEX_LINKED = '11111111-1111-4111-8111-111111111111';

    private const LEX_FOREIGN = '22222222-2222-4222-8222-222222222222';

    private const LEX_NEW = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        foreach ([LexofficePlugin::ID, SevDeskPlugin::ID, EasybillPlugin::ID] as $pluginId) {
            PluginSetting::create([
                'organization_id' => $this->organization->id,
                'plugin_id' => $pluginId,
                'enabled' => true,
                'settings' => ['api_key' => 'k'],
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'company' => 'ACME GmbH',
            'number' => 'K-1001',
            'number_source' => 'local',
            'vat_id' => 'DE111111117',
            'email' => 'info@acme.test',
        ], $attributes));
    }

    private function link(Customer $customer, string $pluginId, string $externalId, string $type = ExternalReference::TYPE_CONTACT): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'external_type' => $type,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => $externalId,
        ]);
    }

    private static function sent(FakePluginHttp $fake, string $method, string $pathEnd): bool {
        foreach ($fake->recorded() as $entry) {
            if ($entry['request']->getMethod() === $method && str_ends_with($entry['request']->getUri()->getPath(), $pathEnd)) {
                return true;
            }
        }

        return false;
    }

    // ── Lexoffice ───────────────────────────────────────────────────────

    public function test_lexoffice_updates_a_linked_contact_by_its_id(): void {
        $customer = $this->customer(['email' => 'neu@acme.test']);
        $this->link($customer, LexofficePlugin::ID, self::LEX_LINKED);
        $fake = FakePluginHttp::fake([
            // GET (Version, Nummer) und PUT teilen das Muster: je Anfrage eine frische Antwort.
            'https://api.lexware.io/v1/contacts/*' => fn () => FakePluginHttp::response(['id' => self::LEX_LINKED, 'resourceUri' => 'x', 'version' => 3, 'roles' => ['customer' => ['number' => 10001]]]),
        ]);

        $this->assertSame(self::LEX_LINKED, app(LexofficePlugin::class)->pushContact($customer));

        $this->assertTrue(self::sent($fake, 'PUT', '/contacts/' . self::LEX_LINKED));
        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST');
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains($r->getUri()->getQuery(), 'email='));
        $this->assertSame('10001', $customer->fresh()?->accounting_number);
    }

    public function test_lexoffice_does_not_overwrite_a_foreign_contact_found_by_email(): void {
        $customer = $this->customer();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts?*' => FakePluginHttp::response(['content' => [
                ['id' => self::LEX_FOREIGN, 'version' => 1, 'company' => ['name' => 'Steuerbüro Fremd', 'vatRegistrationId' => 'DE999999999']],
            ]]),
            'https://api.lexware.io/v1/contacts/*' => FakePluginHttp::response(['id' => self::LEX_NEW, 'version' => 1, 'roles' => ['customer' => ['number' => 10002]]]),
            'https://api.lexware.io/v1/contacts' => FakePluginHttp::response(['id' => self::LEX_NEW, 'resourceUri' => 'x', 'version' => 1]),
        ]);

        $this->assertSame(self::LEX_NEW, app(LexofficePlugin::class)->pushContact($customer));

        $this->assertFalse(self::sent($fake, 'PUT', '/contacts/' . self::LEX_FOREIGN));
        $this->assertTrue(self::sent($fake, 'POST', '/contacts'));
    }

    public function test_lexoffice_only_links_a_match_when_accounting_leads(): void {
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        $customer = $this->customer();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts?*' => FakePluginHttp::response(['content' => [
                ['id' => self::LEX_FOREIGN, 'version' => 1, 'company' => ['name' => 'ACME GmbH', 'vatRegistrationId' => 'DE111111117'], 'roles' => ['customer' => ['number' => 10003]]],
            ]]),
        ]);

        $this->assertSame(self::LEX_FOREIGN, app(LexofficePlugin::class)->pushContact($customer));

        $fake->assertNotSent(fn (RequestInterface $r): bool => in_array($r->getMethod(), ['PUT', 'POST'], true));
        $this->assertSame('10003', $customer->fresh()?->accounting_number);
    }

    // ── sevDesk ─────────────────────────────────────────────────────────

    public function test_sevdesk_refuses_a_number_held_by_another_contact(): void {
        $customer = $this->customer();
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact?*' => FakePluginHttp::response(['objects' => [['id' => '777', 'customerNumber' => 'K-1001', 'name' => 'Fremdfirma GmbH']]]),
        ]);

        try {
            app(SevDeskPlugin::class)->pushContact($customer);
            $this->fail('Fremder Kontakt mit derselben Nummer wurde nicht erkannt.');
        } catch (ContactNumberTakenException $e) {
            $this->assertStringContainsString('Fremdfirma GmbH', $e->getMessage());
        }
        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT');
    }

    public function test_sevdesk_issues_the_next_number_when_it_leads(): void {
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Customer, ['source' => SevDeskPlugin::ID]);
        $customer = $this->customer(['number' => null, 'number_source' => null]);
        $this->assertTrue(NumberSequenceService::isDraft($customer->number));
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact/Factory/getNextCustomerNumber' => FakePluginHttp::response(['objects' => '1042']),
            'https://my.sevdesk.de/api/v1/Contact' => FakePluginHttp::response(['objects' => ['id' => '4242', 'customerNumber' => '1042']]),
            'https://my.sevdesk.de/api/v1/*' => FakePluginHttp::response(['objects' => ['id' => '1']]),
        ]);

        app(SevDeskPlugin::class)->pushContact($customer);

        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getBody(), 'ENTWURF-'));
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST'
            && str_ends_with($r->getUri()->getPath(), '/Contact')
            && str_contains((string) $r->getBody(), '"customerNumber":"1042"'));
        $this->assertSame('1042', $customer->fresh()?->number);
    }

    public function test_sevdesk_updates_a_linked_contact_without_searching(): void {
        $customer = $this->customer();
        $this->link($customer, SevDeskPlugin::ID, '555');
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact/555' => FakePluginHttp::response(['objects' => ['id' => '555', 'customerNumber' => 'K-1001']]),
            'https://my.sevdesk.de/api/v1/*' => FakePluginHttp::response(['objects' => ['id' => '1']]),
        ]);

        app(SevDeskPlugin::class)->pushContact($customer);

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && str_ends_with($r->getUri()->getPath(), '/Contact/555'));
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains($r->getUri()->getQuery(), 'customerNumber='));
    }

    // ── easybill ────────────────────────────────────────────────────────

    public function test_easybill_refuses_a_number_held_by_another_customer(): void {
        $customer = $this->customer();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['items' => [['id' => 88, 'number' => 'K-1001', 'company_name' => 'Fremdfirma GmbH']]]),
        ]);

        $this->expectException(ContactNumberTakenException::class);
        try {
            app(EasybillPlugin::class)->pushContact($customer);
        } finally {
            $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT');
        }
    }

    public function test_easybill_assigns_the_number_when_it_leads(): void {
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Customer, ['source' => EasybillPlugin::ID]);
        $customer = $this->customer(['number' => null, 'number_source' => null]);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['id' => 501, 'number' => '20001']),
        ]);

        app(EasybillPlugin::class)->pushContact($customer);

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST'
            && str_contains($r->getUri()->getQuery(), 'type=CUSTOMER')
            && ! str_contains((string) $r->getBody(), '"number"'));
        $this->assertSame('20001', $customer->fresh()?->number);
    }

    // ── orgaMAX ─────────────────────────────────────────────────────────

    private function orgaMaxConnection(): void {
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
    }

    public function test_orgamax_refuses_a_number_held_by_another_customer(): void {
        $this->orgaMaxConnection();
        $customer = $this->customer();
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer?*' => self::listResponse([['id' => 'om-9', 'number' => 'K-1001', 'name' => 'Fremdfirma GmbH']]),
        ]);

        $this->expectException(ContactNumberTakenException::class);
        try {
            app(OrgaMaxPlugin::class)->pushContact($customer);
        } finally {
            $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT');
        }
    }

    public function test_orgamax_updates_a_customer_linked_by_the_master_data_import(): void {
        $this->orgaMaxConnection();
        $customer = $this->customer();
        $this->link($customer, OrgaMaxPlugin::ID, 'om-1', 'customer');
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer/om-1' => self::itemResponse(['id' => 'om-1']),
        ]);

        $this->assertSame('om-1', app(OrgaMaxPlugin::class)->pushContact($customer));

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && str_ends_with($r->getUri()->getPath(), '/customer/om-1'));
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains($r->getUri()->getQuery(), 'number='));
    }
}
