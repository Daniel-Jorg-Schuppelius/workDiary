<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\Customer;
use App\Models\ExternalReference;
use App\Models\Project;
use App\Models\User;
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_index_shows_active_customers_and_paginates(): void {
        Customer::factory()->count(30)->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertViewHas('customers');
        $paginator = $response->viewData('customers');
        $this->assertSame(30, $paginator->total());
        $this->assertSame(25, $paginator->perPage());
    }

    public function test_index_search_filters_by_name(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'name' => 'ACME GmbH',
        ]);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'name' => 'Foobar AG',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.index', ['q' => 'acme']));
        $response->assertOk();
        $this->assertSame(1, $response->viewData('customers')->total());
    }

    public function test_store_auto_assigns_customer_number(): void {
        $this->actingAs($this->admin)
            ->post(route('customers.store'), [
                'name' => 'Neuer Kunde',
                'currency' => 'EUR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Neuer Kunde',
            'number' => 'K-0001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_increments_customer_number_per_org(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'number' => 'K-0007',
        ]);

        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Folgekunde', 'currency' => 'EUR'])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Folgekunde', 'number' => 'K-0008']);
    }

    public function test_store_persists_contact_persons(): void {
        $this->actingAs($this->admin)
            ->post(route('customers.store'), [
                'name' => 'Mit Kontakten',
                'currency' => 'EUR',
                'contact_persons' => [
                    ['name' => 'Anna Beispiel', 'email' => 'anna@example.com', 'phone' => '+49 1', 'primary' => '1'],
                    ['name' => '', 'email' => '', 'phone' => ''], // leer → wird gefiltert
                    ['name' => 'Bert Test', 'email' => 'bert@example.com', 'phone' => ''],
                ],
            ])
            ->assertRedirect();

        $customer = Customer::where('name', 'Mit Kontakten')->firstOrFail();
        $persons = $customer->contact_persons ?? [];
        $this->assertCount(2, $persons);
        $this->assertSame('Anna Beispiel', $persons[0]['name'] ?? null);
        $this->assertTrue($persons[0]['primary'] ?? false);
    }

    public function test_destroy_blocked_when_projects_exist(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);
        Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_destroy_blocked_when_external_references_exist(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'lex-uuid-1',
            'payload' => null,
            'synced_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_destroy_succeeds_when_clean(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_regular_user_cannot_delete_customer(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();
    }

    public function test_csv_export_returns_streamed_response(): void {
        Customer::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $this->getStreamContent($response->baseResponse);
        $this->assertStringContainsString('Nummer;Name;', $body);
        // Header + 3 Zeilen → mindestens 4 Newlines
        $this->assertGreaterThanOrEqual(4, substr_count($body, "\n"));
    }

    public function test_audit_log_is_written_on_create_and_update(): void {
        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => 'Audit Co.', 'currency' => 'EUR'])
            ->assertRedirect();

        $customer = Customer::where('name', 'Audit Co.')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('customers.update', $customer), [
                'name' => 'Audit Co. (geändert)',
                'currency' => 'EUR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_csv_import_creates_and_updates_customers(): void {
        $existing = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'number' => 'K-0042',
            'name' => 'Alt',
        ]);

        $csv = "\xEF\xBB\xBFNummer;Name;Firma;E-Mail;Stundensatz;Abrechenbar\n"
            . "K-0042;Neu Name;ACME GmbH;info@acme.test;95,50;ja\n"
            . "K-0099;Frischer Kunde;Foo AG;hello@foo.test;120,00;1\n"
            . ";Ohne Nummer wird ggf. neu;;;\n";

        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $csv);
        $upload = new UploadedFile($tmp, 'customers.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->admin)
            ->post(route('customers.import'), ['file' => $upload]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $existing->id, 'name' => 'Neu Name', 'hourly_rate' => 95.5]);
        $this->assertDatabaseHas('customers', ['number' => 'K-0099', 'name' => 'Frischer Kunde', 'billable' => true]);
    }

    public function test_csv_import_requires_billing_permission(): void {
        $csv = "Nummer;Name\nK-0001;Foo\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $csv);
        $upload = new UploadedFile($tmp, 'customers.csv', 'text/csv', null, true);

        $this->actingAs($this->user)
            ->post(route('customers.import'), ['file' => $upload])
            ->assertForbidden();
    }

    public function test_customer_supports_tags_and_attachments_relations(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertInstanceOf(MorphMany::class, $customer->attachments());
        $this->assertInstanceOf(MorphToMany::class, $customer->tags());
        $this->assertSame(0, $customer->attachments()->count());
        $this->assertSame(0, $customer->tags()->count());
    }

    private function getStreamContent(Response $response): string {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
