<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNameTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Stammdaten;

use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Models\Supplier\Supplier;
use App\Services\Customer\Import\CustomerSpec;
use App\Services\Export\Specs\CustomerExportSpec;
use App\Services\Sales\LeadService;
use App\Services\Stammdaten\{ContactPersons, CustomerMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Phase 142 (MVP-1136/1137): Ansprechpartner mit Anrede und Namensteilen —
 * Abgleich mit `contact_name`, Maske, Lead, Import, Export, Zusammenführen,
 * Bestandsübernahme und die Liste weiterer Ansprechpartner.
 */
class ContactNameTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(array_merge(['organization_id' => $this->organization->id, 'contact_name' => null], $attributes));
    }

    public function test_parts_build_the_contact_name(): void {
        $customer = $this->customer([
            'contact_salutation' => ContactSalutation::Ms,
            'contact_title' => 'Dr.',
            'contact_first_name' => 'Anna',
            'contact_middle_names' => 'Maria',
            'contact_last_name' => 'Müller',
        ]);

        $this->assertSame('Dr. Anna Maria Müller', $customer->contact_name);
        $this->assertSame('Frau Dr. Anna Maria Müller', $customer->contactNameWithSalutation());

        $customer->update(['contact_first_name' => null, 'contact_middle_names' => null]);
        $this->assertSame('Dr. Müller', $customer->fresh()?->contact_name);
    }

    public function test_a_bare_contact_name_is_split(): void {
        $customer = $this->customer(['contact_name' => 'Herr Prof. Dr. Klaus Peter von Berg']);

        $this->assertSame(ContactSalutation::Mr, $customer->contact_salutation);
        $this->assertSame('Prof. Dr.', $customer->contact_title);
        $this->assertSame('Klaus', $customer->contact_first_name);
        $this->assertSame('Peter', $customer->contact_middle_names);
        $this->assertSame('von Berg', $customer->contact_last_name);
        $this->assertSame('Herr Prof. Dr. Klaus Peter von Berg', $customer->contact_name);
    }

    public function test_form_saves_the_parts_and_shows_the_salutation(): void {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Muster GmbH',
            'company' => 'Muster GmbH',
            'currency' => 'EUR',
            'contact_salutation' => 'ms',
            'contact_title' => 'Dr.',
            'contact_first_name' => 'Eva',
            'contact_last_name' => 'Weiß',
        ])->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'Muster GmbH')->firstOrFail();
        $this->assertSame('Dr. Eva Weiß', $customer->contact_name);
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertSee('Frau Dr. Eva Weiß');
        $this->actingAs($this->admin)->get(route('customers.edit', $customer))
            ->assertSee('name="contact_first_name"', false)
            ->assertSee('name="contact_middle_names"', false)
            ->assertSee('name="contact_salutation"', false)
            ->assertDontSee('name="contact_name"', false);
    }

    public function test_supplier_form_saves_the_parts(): void {
        $this->actingAs($this->admin)->post(route('suppliers.store'), [
            'name' => 'Lieferwerk KG',
            'currency' => 'EUR',
            'contact_salutation' => 'mr',
            'contact_first_name' => 'Jan',
            'contact_last_name' => 'Meier',
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::query()->where('name', 'Lieferwerk KG')->firstOrFail();
        $this->assertSame('Jan Meier', $supplier->contact_name);
        $this->assertSame(ContactSalutation::Mr, $supplier->contact_salutation);
    }

    public function test_lead_with_a_person_but_no_company_is_accepted(): void {
        $this->actingAs($this->admin)->post(route('leads.store'), [
            'source' => 'web',
            'contact_first_name' => 'Tom',
            'contact_last_name' => 'Bauer',
        ])->assertRedirect();

        $this->assertSame('Tom Bauer', Lead::query()->firstOrFail()->contact_name);
    }

    public function test_lead_anonymisation_clears_the_parts(): void {
        $lead = Lead::query()->create([
            'organization_id' => $this->organization->id,
            'source' => 'web',
            'contact_name' => 'Frau Lisa Lang',
            'created_by' => $this->admin->id,
        ]);
        $this->assertSame('Lang', $lead->contact_last_name);

        app(LeadService::class)->anonymize($lead);

        $lead->refresh();
        $this->assertNull($lead->contact_salutation);
        $this->assertNull($lead->contact_first_name);
        $this->assertNull($lead->contact_last_name);
    }

    public function test_import_reads_separate_columns_and_free_text(): void {
        $spec = new CustomerSpec();

        $row = $spec->normalize(['name' => 'Spaltenkunde', 'contact_salutation' => 'Frau', 'contact_first_name' => 'Ida', 'contact_last_name' => 'Kern']);
        $this->assertSame('ms', $row['contact_salutation']);
        $spec->upsert($row, $this->organization);

        $spec->upsert($spec->normalize(['name' => 'Textkunde', 'contact_name' => 'Herr Dr. Udo Lenz']), $this->organization);

        $columns = Customer::query()->where('name', 'Spaltenkunde')->firstOrFail();
        $this->assertSame('Ida Kern', $columns->contact_name);
        $this->assertSame(ContactSalutation::Ms, $columns->contact_salutation);
        $text = Customer::query()->where('name', 'Textkunde')->firstOrFail();
        $this->assertSame(ContactSalutation::Mr, $text->contact_salutation);
        $this->assertSame('Dr.', $text->contact_title);
        $this->assertSame('Lenz', $text->contact_last_name);
    }

    public function test_export_carries_the_parts(): void {
        $customer = $this->customer(['contact_name' => 'Frau Anna Maria Müller']);

        $row = (new CustomerExportSpec())->toRow($customer);

        $this->assertSame('ms', $row['contact_salutation']);
        $this->assertSame('Anna', $row['contact_first_name']);
        $this->assertSame('Maria', $row['contact_middle_names']);
        $this->assertSame('Müller', $row['contact_last_name']);
    }

    public function test_merge_takes_the_contact_with_its_salutation(): void {
        $source = $this->customer(['name' => 'Quelle', 'contact_salutation' => ContactSalutation::Ms, 'contact_first_name' => 'Rita', 'contact_last_name' => 'Roth']);
        $target = $this->customer(['name' => 'Ziel']);

        app(CustomerMergeService::class)->merge($source, $target);

        $target->refresh();
        $this->assertSame('Rita Roth', $target->contact_name);
        $this->assertSame(ContactSalutation::Ms, $target->contact_salutation);
        $this->assertSame('Rita', $target->contact_first_name);
    }

    public function test_migration_splits_the_existing_contacts(): void {
        $customer = $this->customer(['name' => 'Altbestand']);
        DB::table('customers')->where('id', $customer->id)->update([
            'contact_name' => 'Herr Dr. Max Müller',
            'contact_salutation' => null,
            'contact_title' => null,
            'contact_first_name' => null,
            'contact_middle_names' => null,
            'contact_last_name' => null,
            'contact_persons' => json_encode([['name' => 'Frau Petra Schmidt', 'email' => 'p@x.test', 'primary' => true]]),
        ]);

        $migration = require database_path('migrations/2027_03_18_100100_add_contact_name_parts.php');
        $migration->up();

        $customer->refresh();
        $this->assertSame('Herr Dr. Max Müller', $customer->contact_name);
        $this->assertSame(ContactSalutation::Mr, $customer->contact_salutation);
        $this->assertSame('Dr.', $customer->contact_title);
        $this->assertSame('Müller', $customer->contact_last_name);
        $this->assertSame('ms', $customer->contact_persons[0]['salutation'] ?? null);
        $this->assertSame('Schmidt', $customer->contact_persons[0]['last_name'] ?? null);
        $this->assertSame('Frau Petra Schmidt', $customer->contact_persons[0]['name'] ?? null);
    }

    public function test_contact_person_list_is_stored_with_parts(): void {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Listenkunde',
            'currency' => 'EUR',
            'contact_persons' => [
                ['salutation' => 'mr', 'title' => 'Prof.', 'first_name' => 'Karl', 'middle_names' => 'Heinz', 'last_name' => 'Berg', 'email' => 'k@x.test', 'primary' => '1'],
                ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $persons = Customer::query()->where('name', 'Listenkunde')->firstOrFail()->contact_persons;
        $this->assertCount(1, $persons);
        $this->assertSame('Prof. Karl Heinz Berg', $persons[0]['name']);
        $this->assertSame('Herr Prof. Karl Heinz Berg', ContactPersons::displayName($persons[0]));
        $this->assertTrue($persons[0]['primary']);
    }

    public function test_a_bare_name_in_the_list_is_split(): void {
        $entry = ContactPersons::normalize(['name' => 'Frau Ute Vogel', 'phone' => '123']);

        $this->assertSame('ms', $entry['salutation'] ?? null);
        $this->assertSame('Ute', $entry['first_name'] ?? null);
        $this->assertSame('Vogel', $entry['last_name'] ?? null);
        $this->assertSame('Ute Vogel', $entry['name'] ?? null);
        $this->assertNull(ContactPersons::normalize(['name' => ' ', 'email' => '']));
    }
}
