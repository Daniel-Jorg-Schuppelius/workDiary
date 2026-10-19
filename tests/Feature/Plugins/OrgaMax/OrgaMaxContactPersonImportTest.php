<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxContactPersonImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\OrgaMax;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxSyncService;
use App\Services\Integration\InboxActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * orgaMAX-Stammdatenimport mit Ansprechpartnern: Hauptkontakt und Liste
 * stehen im Inbox-Datensatz und in der Anlage daraus; an einem verknüpften
 * Kunden wird der Ansprechpartner als Feldkonflikt geführt, der Enum-Cast
 * der Anrede bricht den Vergleich nicht.
 */
class OrgaMaxContactPersonImportTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private OrgaMaxConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's', 'ownership_id' => 'own-1',
            'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(), 'status' => OrgaMaxConnectionStatus::Active,
            'capabilities' => ['customers' => ['enabled' => true, 'leader' => 'orgamax']],
        ]);
    }

    public function test_the_inbox_record_carries_the_main_contact_and_the_list_and_creation_uses_them(): void {
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([[
                'id' => 'om-9', 'name' => 'Stimmgerecht oHG', 'companyName' => 'Stimmgerecht oHG', 'email' => 'info@stimmgerecht.test', 'contactPersons' => [
                    ['salutation' => 'Herr', 'firstName' => 'Ralph', 'lastName' => 'Hartig', 'isMainContact' => false],
                    ['salutation' => 'Frau', 'firstName' => 'Birgit', 'lastName' => 'Hartig', 'isMainContact' => true],
                ],
            ]]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);

        app(OrgaMaxSyncService::class)->run($this->connection);

        $item = IntegrationInboxItem::query()->where('plugin_id', OrgaMaxPlugin::ID)->where('status', IntegrationInboxStatus::Open)->sole();
        $this->assertSame(['ms', 'Birgit', 'Hartig'], [$item->mapped_snapshot['contact_salutation'] ?? null, $item->mapped_snapshot['contact_first_name'] ?? null, $item->mapped_snapshot['contact_last_name'] ?? null]);
        $this->assertSame(['Ralph', 'Birgit'], array_column($item->mapped_snapshot['contact_persons'] ?? [], 'first_name'));

        $customer = app(InboxActionService::class)->createFromItem($item);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame([ContactSalutation::Ms, 'Birgit Hartig'], [$customer->contact_salutation, $customer->contact_name]);
        $this->assertCount(2, $customer->contact_persons ?? []);
    }

    public function test_a_linked_customer_gets_a_field_conflict_for_the_contact_without_breaking_on_the_enum_cast(): void {
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'ACME GmbH', 'email' => 'billing@acme.test', 'currency' => 'EUR', 'created_by' => $this->admin->id,
            'contact_salutation' => ContactSalutation::Mr, 'contact_first_name' => 'Karl', 'contact_last_name' => 'Berg']);
        ExternalReference::create(['organization_id' => $this->organization->id, 'plugin_id' => OrgaMaxPlugin::ID, 'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => 'om-1']);
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([
                ['id' => 'om-1', 'name' => 'ACME GmbH', 'email' => 'billing@acme.test', 'contactPersons' => [['salutation' => 'Herr', 'firstName' => 'Karl', 'lastName' => 'Berg', 'isMainContact' => true]]],
            ]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);
        app(OrgaMaxSyncService::class)->run($this->connection);
        // Gleicher Hauptansprechpartner, nur die Liste ist lokal leer → genau dieses Feld weicht ab.
        $item = IntegrationInboxItem::query()->where('plugin_id', OrgaMaxPlugin::ID)->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->sole();
        $this->assertSame(['contact_persons'], $item->diff_fields);

        $customer->update(['contact_persons' => [['salutation' => 'mr', 'first_name' => 'Karl', 'last_name' => 'Berg', 'primary' => true]]]);
        $item->delete();
        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/customer*' => self::listResponse([
                ['id' => 'om-1', 'name' => 'ACME GmbH', 'email' => 'billing@acme.test', 'contactPersons' => [['salutation' => 'Frau', 'firstName' => 'Anna', 'lastName' => 'Berg', 'isMainContact' => true]]],
            ]),
            'https://api.orgamax.de/openapi/invoice*' => self::listResponse([]),
        ]);
        app(OrgaMaxSyncService::class)->run($this->connection->refresh());

        $item = IntegrationInboxItem::query()->where('plugin_id', OrgaMaxPlugin::ID)->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->sole();
        $this->assertEqualsCanonicalizing(['contact_salutation', 'contact_first_name', 'contact_persons'], $item->diff_fields);
        $this->assertSame('Karl', $customer->refresh()->contact_first_name);
    }
}
