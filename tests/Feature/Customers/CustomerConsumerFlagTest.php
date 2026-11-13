<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerConsumerFlagTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Customer\Import\CustomerSpec;
use App\Services\Customer\Mcp\{CreateCustomerTool, CustomersTool};
use App\Services\Export\Specs\CustomerExportSpec;
use App\Services\Import\ImportOutcome;
use App\Services\Mcp\WorkDiaryMcpServer;
use App\Services\Privacy\SubjectData\CustomerMasterDataSection;
use App\Services\Stammdaten\CustomerMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1688: Merkmal „Verbraucher“ am Kunden (Leistungsort nach § 3a UStG).
 * Dialog, Detailseite, Validierung, CSV, API, MCP, Zusammenführen und
 * Auskunft führen es; ohne Angabe gilt der Kunde als Unternehmen.
 */
final class CustomerConsumerFlagTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_dialog_writes_the_flag_and_the_detail_page_shows_it(): void {
        $this->actingAs($this->admin)->post(route('customers.store'), $this->form() + ['is_consumer' => '1'])->assertSessionHasNoErrors();
        $customer = Customer::query()->where('name', 'Erika Beispiel')->firstOrFail();
        $this->assertTrue($customer->is_consumer);

        $this->actingAs($this->admin)->get(route('customers.edit', [$customer, 'dialog' => 1]))
            ->assertOk()
            ->assertSee('name="is_consumer"', false);
        $this->actingAs($this->admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('invoicing.consumer.recipient'))
            ->assertSee(__('invoicing.consumer.yes'));

        // Das Formular sendet den Haken immer (verstecktes Feld): abgewählt ist der Kunde wieder Unternehmen.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), $this->form() + ['is_consumer' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($customer->refresh()->is_consumer);
    }

    public function test_a_consumer_is_never_a_reverse_charge_recipient(): void {
        $this->actingAs($this->admin)->post(route('customers.store'), $this->form() + ['is_consumer' => '1', 'is_reverse_charge_recipient' => '1'])
            ->assertSessionHasErrors(['is_reverse_charge_recipient' => __('invoicing.consumer.reverse_charge_conflict')]);

        $this->actingAs($this->admin)->post(route('customers.store'), $this->form() + ['is_consumer' => '0', 'is_reverse_charge_recipient' => '1'])
            ->assertSessionHasNoErrors();
    }

    public function test_csv_import_and_export_carry_the_flag(): void {
        $spec = new CustomerSpec();
        $row = $spec->normalize(['name' => 'Importkunde', 'number' => 'K-IMP-9', 'currency' => 'EUR', 'is_consumer' => 'ja']);
        $this->assertSame([], $spec->validateRow($row, $this->organization));

        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $customer = Customer::query()->where('number', 'K-IMP-9')->firstOrFail();
        $this->assertTrue($customer->is_consumer);
        $this->assertSame('1', app(CustomerExportSpec::class)->toRow($customer)['is_consumer']);
        $this->assertContains('is_consumer', $spec->columns());
        $this->assertSame('is_consumer', $spec->headerAliases()['verbraucher']);

        // Ohne Spalte bleibt der Wert, ein leerer Bestand gilt als Unternehmen.
        [$again] = $spec->upsert($spec->normalize(['name' => 'Importkunde', 'number' => 'K-IMP-9', 'currency' => 'EUR']), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $again);
        $this->assertTrue($customer->refresh()->is_consumer);
        $this->assertSame('0', app(CustomerExportSpec::class)->toRow($this->customer())['is_consumer']);
    }

    public function test_api_reads_and_writes_the_flag_and_keeps_it_without_the_field(): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(route('api.customers.store'), ['name' => 'API-Verbraucher', 'currency' => 'EUR', 'is_consumer' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_consumer', true);
        $customer = Customer::query()->where('name', 'API-Verbraucher')->firstOrFail();

        $this->putJson(route('api.customers.update', $customer), ['name' => 'API-Verbraucher', 'currency' => 'EUR'])
            ->assertOk()
            ->assertJsonPath('data.is_consumer', true);
        $this->putJson(route('api.customers.update', $customer), ['name' => 'API-Verbraucher', 'currency' => 'EUR', 'is_consumer' => false])
            ->assertOk()
            ->assertJsonPath('data.is_consumer', false);

        $this->postJson(route('api.customers.store'), ['name' => 'API-Firma', 'currency' => 'EUR'])
            ->assertCreated()
            ->assertJsonPath('data.is_consumer', false);
    }

    public function test_mcp_creates_and_shows_the_flag(): void {
        $this->organization->update(['settings' => ['mcp' => ['enabled' => '1']]]);
        $this->admin->unsetRelation('roles')->unsetRelation('permissions');
        Sanctum::actingAs($this->admin, ['mcp:write', 'mcp:read']);

        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'MCP-Verbraucher', 'is_consumer' => true])->assertOk()->assertHasNoErrors();
        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'MCP-Firma', 'is_consumer' => null])->assertOk()->assertHasNoErrors();

        $consumer = Customer::query()->where('name', 'MCP-Verbraucher')->firstOrFail();
        $this->assertTrue($consumer->is_consumer);
        $this->assertFalse(Customer::query()->where('name', 'MCP-Firma')->firstOrFail()->is_consumer);
        WorkDiaryMcpServer::tool(CustomersTool::class, ['id' => $consumer->sqid])
            ->assertOk()
            ->assertSee('"is_consumer":true');
    }

    public function test_merge_keeps_the_target_flag_unless_the_source_is_chosen(): void {
        $target = $this->customer(['name' => 'Ziel']);
        $source = $this->customer(['name' => 'Quelle', 'is_consumer' => true]);

        app(CustomerMergeService::class)->merge($source, $target);
        $this->assertFalse($target->refresh()->is_consumer, 'Ein Wahrheitswert ist nie leer — das Ziel behält seinen.');

        $chosen = $this->customer(['name' => 'Zweite Quelle', 'is_consumer' => true]);
        $this->actingAs($this->admin)->get(route('customers.duplicates.compare', ['source' => $chosen->sqid, 'target' => $target->sqid]))
            ->assertOk()
            ->assertSee('value="is_consumer"', false);
        $this->actingAs($this->admin)->post(route('customers.duplicates.merge'), ['source' => $chosen->sqid, 'target' => $target->sqid, 'prefer_source' => ['is_consumer']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($target->refresh()->is_consumer);
    }

    public function test_subject_access_names_the_recipient_kind(): void {
        $section = app(CustomerMasterDataSection::class)->build($this->customer(['is_consumer' => true]));

        $this->assertSame((string) __('invoicing.consumer.yes'), data_get($section, 'fields.is_consumer.value'));
    }

    /** @return array<string, mixed> */
    private function form(): array {
        return [
            'name' => 'Erika Beispiel',
            'currency' => 'EUR',
            'address_street' => 'Seeweg 3',
            'address_zip' => '8001',
            'address_city' => 'Zürich',
            'country' => 'CH',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Merkmal',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ])->refresh();
    }
}
