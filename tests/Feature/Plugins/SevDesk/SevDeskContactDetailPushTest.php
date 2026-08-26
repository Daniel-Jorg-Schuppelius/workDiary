<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskContactDetailPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Models\{Customer, ExternalReference, PluginSetting};
use App\Plugins\SevDesk\Services\SevDeskContactDetailPushService;
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
 * MVP-731 (Vollscan G18): sevDesk-Adresspush.
 *
 * sevDesk führt Anschrift und Kommunikationswege als eigene Objekte mit
 * eigenem Endpunkt — ohne sie blieb der gepushte Kontakt ein Name. Geprüft
 * wird: beide Endpunkte werden bedient, die eigene E-Mail der Organisation
 * geht NIE mit, und ein zweiter Lauf aktualisiert (PUT) statt anzulegen.
 */
class SevDeskContactDetailPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-123'],
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'number' => 'K-1001',
            'email' => 'billing@acme.test',
            'phone' => '+49 30 1234567',
            'address_street' => 'Hauptstr. 1',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'created_by' => $this->orgAdmin()->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/SevDesk/contact-detail.json')), true);

        return $data;
    }

    private function fakeApi(): FakePluginHttp {
        $fx = $this->fixture();

        return FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact?*' => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Contact' => FakePluginHttp::response(['objects' => ['id' => '4242']]),
            'https://my.sevdesk.de/api/v1/ContactAddress*' => FakePluginHttp::response($fx['address']),
            'https://my.sevdesk.de/api/v1/CommunicationWay*' => [
                FakePluginHttp::response($fx['email']),
                FakePluginHttp::response($fx['phone']),
            ],
        ]);
    }

    public function test_contact_push_also_creates_address_and_communication_ways(): void {
        $fake = $this->fakeApi();

        $contactId = app(SevDeskPlugin::class)->pushContact($this->customer);

        $this->assertSame('4242', $contactId);
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST'
            && str_ends_with((string) $r->getUri()->getPath(), '/ContactAddress'));
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST'
            && str_ends_with((string) $r->getUri()->getPath(), '/CommunicationWay'));

        // Idempotenz-Nachweis: je Objekt eine external_id am Kunden.
        $types = ExternalReference::query()
            ->forPlugin($this->organization->id, SevDeskPlugin::ID)
            ->pluck('external_id', 'external_type')
            ->all();
        $this->assertSame('5501', $types[SevDeskContactDetailPushService::TYPE_ADDRESS] ?? null);
        $this->assertSame('6601', $types[SevDeskContactDetailPushService::TYPE_EMAIL] ?? null);
        $this->assertSame('6602', $types[SevDeskContactDetailPushService::TYPE_PHONE] ?? null);
    }

    public function test_second_push_updates_the_known_objects_instead_of_adding_more(): void {
        $this->fakeApi();
        app(SevDeskPlugin::class)->pushContact($this->customer);

        $fake = $this->fakeApi();
        app(SevDeskPlugin::class)->pushContact($this->customer);

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT'
            && str_contains((string) $r->getUri()->getPath(), '/ContactAddress/5501'));
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'PUT'
            && str_contains((string) $r->getUri()->getPath(), '/CommunicationWay/6601'));
        $fake->assertNotSent(fn (RequestInterface $r): bool => $r->getMethod() === 'POST'
            && str_ends_with((string) $r->getUri()->getPath(), '/ContactAddress'));

        $this->assertSame(3, ExternalReference::query()
            ->forPlugin($this->organization->id, SevDeskPlugin::ID)
            ->whereIn('external_type', [
                SevDeskContactDetailPushService::TYPE_ADDRESS,
                SevDeskContactDetailPushService::TYPE_EMAIL,
                SevDeskContactDetailPushService::TYPE_PHONE,
            ])->count());
    }

    public function test_own_organization_email_is_never_pushed_to_a_foreign_contact(): void {
        Setting::set('einvoice.contact_email', 'billing@acme.test', SettingScope::Organization, $this->organization);
        $this->customer->forceFill(['phone' => null])->save();

        $fake = $this->fakeApi();
        app(SevDeskPlugin::class)->pushContact($this->customer);

        $fake->assertNotSent(fn (RequestInterface $r): bool => str_ends_with((string) $r->getUri()->getPath(), '/CommunicationWay'));
        $this->assertNull(ExternalReference::query()
            ->forPlugin($this->organization->id, SevDeskPlugin::ID, SevDeskContactDetailPushService::TYPE_EMAIL)
            ->first());
    }

    public function test_customer_without_address_pushes_no_empty_address(): void {
        $this->customer->forceFill([
            'address_street' => null, 'address_zip' => null, 'address_city' => null,
        ])->save();

        $fake = $this->fakeApi();
        app(SevDeskPlugin::class)->pushContact($this->customer);

        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri()->getPath(), '/ContactAddress'));
        $this->assertTrue(app(ContactPushService::class)->pushAllowed());
    }
}
