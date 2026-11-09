<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierContactPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\Numbering\{ContactNumberOutcome, NumberScope};
use App\Models\Integration\ExternalReference;
use App\Models\Platform\PluginSetting;
use App\Models\Supplier\Supplier;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Plugins\OrgaMax\OrgaMaxApiResponses;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Lieferanten an sevDesk, easybill und orgaMAX (MVP-1503): Übertragung über
 * {@see ContactPushService::pushSupplier()}, Nummernvergabe durch easybill
 * (`type=SUPPLIER`) und orgaMAX (`number` beim Anlegen), sevDesk mit der
 * Kontaktkategorie „Lieferant“ (ID 2) und ohne Lieferantenkreis.
 */
final class SupplierContactPushTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        foreach ([SevDeskPlugin::ID, EasybillPlugin::ID, OrgaMaxPlugin::ID] as $pluginId) {
            PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function supplier(array $attributes = []): Supplier {
        return Supplier::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Holzhandel Birkenfeld GmbH',
            'number' => 'L-2001',
            'number_source' => NumberAuthority::SOURCE_LOCAL,
            'vat_id' => 'DE222222224',
            'email' => 'einkauf@birkenfeld.test',
            'phone' => '0711 123456',
        ]);
    }

    private function reference(Supplier $supplier, string $pluginId): ?ExternalReference {
        return ExternalReference::query()->forPlugin($this->organization->id, $pluginId, ExternalReference::TYPE_CONTACT)->forReferenceable($supplier)->first();
    }

    /** @param  array<string, mixed>  $capabilities */
    private function orgaMaxConnection(array $capabilities): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's',
            'ownership_id' => 'own-1', 'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active, 'capabilities' => $capabilities,
        ]);
    }

    public function test_sevdesk_creates_a_contact_in_the_supplier_category_and_updates_it_later(): void {
        $supplier = $this->supplier();
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Contact/8801' => FakePluginHttp::response(['objects' => ['id' => '8801', 'objectName' => 'Contact']]),
            'https://my.sevdesk.de/api/v1/Contact' => FakePluginHttp::response(['objects' => ['id' => '8801', 'objectName' => 'Contact']], 201),
            'https://my.sevdesk.de/api/v1/ContactAddress*' => FakePluginHttp::response(['objects' => ['id' => '91']]),
            'https://my.sevdesk.de/api/v1/CommunicationWay*' => FakePluginHttp::response(['objects' => ['id' => '92']]),
        ]);

        $this->assertSame('8801', app(ContactPushService::class)->pushSupplier($supplier, SevDeskPlugin::ID));

        $fake->assertSent(static function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && str_ends_with($request->getUri()->getPath(), '/Contact')
                && data_get($body, 'category') === ['id' => 2, 'objectName' => 'Category']
                && data_get($body, 'name') === 'Holzhandel Birkenfeld GmbH'
                && ! array_key_exists('customerNumber', (array) $body);
        });
        $fake->assertSent(static fn (RequestInterface $request): bool => $request->getMethod() === 'POST' && str_ends_with($request->getUri()->getPath(), '/ContactAddress'));
        $this->assertSame('8801', $this->reference($supplier, SevDeskPlugin::ID)?->external_id);
        $this->assertSame('L-2001', $supplier->fresh()?->number, 'sevDesk führt keinen Lieferantenkreis.');

        app(ContactPushService::class)->pushSupplier($supplier->refresh(), SevDeskPlugin::ID);
        $fake->assertSent(static fn (RequestInterface $request): bool => $request->getMethod() === 'PUT' && str_ends_with($request->getUri()->getPath(), '/Contact/8801'));
    }

    public function test_easybill_issues_the_supplier_number_when_it_numbers_suppliers(): void {
        $supplier = $this->supplier(['number' => NumberSequenceService::DRAFT_PREFIX . '1', 'number_source' => EasybillPlugin::ID]);
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Supplier, ['source' => EasybillPlugin::ID]);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers*' => FakePluginHttp::response(['id' => 5501, 'supplier_number' => '70001', 'company_name' => 'Holzhandel Birkenfeld GmbH']),
        ]);

        $outcome = app(ContactPushService::class)->issueNumber($supplier);

        $this->assertSame(ContactNumberOutcome::Adopted, $outcome);
        $this->assertSame('70001', $supplier->fresh()?->number);
        $this->assertSame('5501', $this->reference($supplier, EasybillPlugin::ID)?->external_id);
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && ($query['type'] ?? null) === 'SUPPLIER'
                && ! array_key_exists('supplier_number', (array) $body) && ! array_key_exists('number', (array) $body)
                && data_get($body, 'vat_identifier') === 'DE222222224';
        });
    }

    public function test_easybill_keeps_a_local_supplier_number_and_never_creates_a_customer_number(): void {
        $supplier = $this->supplier();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers*' => FakePluginHttp::response(['id' => 5502, 'supplier_number' => 'L-2001']),
        ]);

        $this->assertSame('5502', app(ContactPushService::class)->pushSupplier($supplier, EasybillPlugin::ID));

        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);
            $body = json_decode((string) $request->getBody(), true);

            return ($query['type'] ?? null) === 'SUPPLIER' && data_get($body, 'supplier_number') === 'L-2001' && ! array_key_exists('number', (array) $body);
        });
        $this->assertSame('L-2001', $supplier->fresh()?->number);
    }

    public function test_orgamax_writes_suppliers_only_while_workdiary_leads_them(): void {
        $this->orgaMaxConnection(['suppliers' => ['enabled' => true, 'leader' => 'orgamax']]);
        $fake = FakePluginHttp::fake();

        try {
            app(ContactPushService::class)->pushSupplier($this->supplier(), OrgaMaxPlugin::ID);
            $this->fail('orgaMAX führt die Lieferanten — keine Übertragung.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Lieferanten führt orgaMAX', $e->getMessage());
        }
        $fake->assertNothingSent();
    }

    public function test_orgamax_creates_the_supplier_with_address_and_adopts_its_number(): void {
        $this->orgaMaxConnection(['suppliers' => ['enabled' => true, 'leader' => 'workdiary']]);
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Supplier, ['source' => OrgaMaxPlugin::ID]);
        $supplier = $this->supplier(['number' => NumberSequenceService::DRAFT_PREFIX . '2', 'number_source' => OrgaMaxPlugin::ID]);
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/supplier' => FakePluginHttp::response(['meta' => [], 'data' => ['id' => 15, 'number' => 70001, 'name' => 'Holzhandel Birkenfeld GmbH']], 201),
        ]);

        $this->assertSame(ContactNumberOutcome::Adopted, app(ContactPushService::class)->issueNumber($supplier));

        $this->assertSame('70001', $supplier->fresh()?->number);
        $this->assertSame('15', $this->reference($supplier, OrgaMaxPlugin::ID)?->external_id);
        $street = (string) $supplier->refresh()->address_street;
        $fake->assertSent(static function (RequestInterface $request) use ($street): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && str_ends_with($request->getUri()->getPath(), '/openapi/supplier')
                && data_get($body, 'name') === 'Holzhandel Birkenfeld GmbH'
                && data_get($body, 'addresses.0.companyName') === 'Holzhandel Birkenfeld GmbH'
                && data_get($body, 'addresses.0.isDefault') === true
                && data_get($body, 'addresses.0.street') === $street
                && ! array_key_exists('number', (array) $body);
        });
    }

    public function test_orgamax_updates_a_supplier_linked_by_the_master_data_import(): void {
        $this->orgaMaxConnection(['suppliers' => ['enabled' => true, 'leader' => 'workdiary']]);
        $supplier = $this->supplier();
        ExternalReference::link($this->organization->id, OrgaMaxPlugin::ID, 'supplier', $supplier, '16');
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/supplier/16' => FakePluginHttp::response(['meta' => [], 'data' => ['id' => 16, 'number' => 70002]]),
        ]);

        $this->assertSame('16', app(ContactPushService::class)->pushSupplier($supplier, OrgaMaxPlugin::ID));

        $fake->assertSent(static function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'PUT' && str_ends_with($request->getUri()->getPath(), '/supplier/16') && ! array_key_exists('addresses', (array) $body);
        });
        $this->assertSame('L-2001', $supplier->fresh()?->number, 'Eine lokal vergebene Nummer bleibt.');
    }

    public function test_supplier_number_scope_offers_easybill_and_orgamax_but_not_sevdesk(): void {
        $options = app(NumberAuthority::class)->sourceOptions($this->organization, NumberScope::Supplier);

        $this->assertArrayHasKey(EasybillPlugin::ID, $options);
        $this->assertArrayHasKey(OrgaMaxPlugin::ID, $options);
        $this->assertArrayNotHasKey(SevDeskPlugin::ID, $options);
        $this->assertArrayNotHasKey(OrgaMaxPlugin::ID, app(NumberAuthority::class)->sourceOptions($this->organization, NumberScope::Customer), 'Kundennummern vergibt orgaMAX nicht.');
    }
}
