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
use App\Models\{AuditLog, Customer, ExternalReference, Project, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Database\Eloquent\Relations\{MorphMany, MorphToMany};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
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

        $response = $this->getAsUser('customers.index');

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

        $response = $this->getAsUser('customers.index', ['q' => 'acme']);
        $response->assertOk();
        $this->assertSame(1, $response->viewData('customers')->total());
    }

    public function test_show_renders_customer_detail(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'name' => 'Detail Kunde',
            'billable' => false,
        ]);
        Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt Alpha',
            'status' => ProjectStatus::Active->value,
        ]);

        $this->actingAs($this->user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Detail Kunde')
            ->assertSeeText('nicht abrechenbar')
            ->assertSeeText('Kontakt')
            ->assertSeeText('Abrechnung')
            ->assertSeeText('Projekt Alpha');
    }

    public function test_store_auto_assigns_customer_number(): void {
        $this->postAsAdmin('customers.store', [
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

    /** F8/E6 (Vollscan 2026-08-23): contact_* führend, Inline-Spalten sind Projektion. */
    public function test_address_and_bank_inputs_write_contact_records_and_project_inline(): void {
        $this->postAsAdmin('customers.store', [
            'name' => 'Projektions GmbH',
            'currency' => 'EUR',
            'address_street' => 'Musterweg 1',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'country' => 'DE',
            'bank_iban' => 'DE02120300000000202051',
            'bank_account_holder' => 'Projektions GmbH',
        ])->assertRedirect();

        $customer = Customer::query()->where('name', 'Projektions GmbH')->firstOrFail();

        // Quelle der Wahrheit: contact_addresses/contact_bank_accounts …
        $address = $customer->primaryAddress();
        $this->assertSame('Musterweg 1', $address?->street);
        $this->assertSame('DE', $address?->country_code);
        $this->assertSame('DE02120300000000202051', $customer->primaryBankAccount()?->iban);

        // … und die Inline-Spalten sind die nachgezogene Projektion.
        $this->assertSame('Musterweg 1', $customer->address_street);
        $this->assertSame('DE02120300000000202051', $customer->bank_iban);

        // Direkte Änderung an der ContactAddress projiziert ebenfalls.
        $address?->update(['street' => 'Neuer Weg 2']);
        $this->assertSame('Neuer Weg 2', $customer->fresh()?->address_street);
    }

    public function test_store_increments_customer_number_per_org(): void {
        // Greenfield: Kundennummern werden zentral über NumberSequenceService
        // vergeben. Externes Anlegen mit fixer Nummer aktualisiert die
        // Sequenz nicht — entweder per Factory die Sequenz vorsetzen oder
        // (wie hier) explizit den last_value setzen.
        \App\Models\NumberSequence::create([
            'organization_id' => $this->organization->id,
            'scope' => \App\Enums\Numbering\NumberScope::Customer->value,
            'period' => null,
            'last_value' => 7,
        ]);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'number' => 'K-0007',
        ]);

        $this->postAsAdmin('customers.store', ['name' => 'Folgekunde', 'currency' => 'EUR'])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Folgekunde', 'number' => 'K-0008']);
    }

    public function test_store_skips_existing_customer_number_when_sequence_is_new(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'number' => 'K-0001',
        ]);

        $this->postAsAdmin('customers.store', ['name' => 'Folgekunde', 'currency' => 'EUR'])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Folgekunde',
            'number' => 'K-0002',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_skips_more_than_one_hundred_existing_customer_numbers_when_sequence_is_new(): void {
        for ($i = 1; $i <= 125; $i++) {
            Customer::factory()->create([
                'organization_id' => $this->organization->id,
                'created_by' => $this->user->id,
                'number' => 'K-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);
        }

        $this->postAsAdmin('customers.store', ['name' => 'Folgekunde', 'currency' => 'EUR'])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Folgekunde',
            'number' => 'K-0126',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_rejects_duplicate_customer_number_in_current_org(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'number' => 'K-0001',
        ]);

        $this->postAsAdmin('customers.store', [
            'name' => 'Doppelter Kunde',
            'number' => 'K-0001',
            'currency' => 'EUR',
        ])->assertSessionHasErrors('number');

        $this->assertDatabaseMissing('customers', [
            'name' => 'Doppelter Kunde',
            'number' => 'K-0001',
        ]);
    }

    public function test_store_persists_contact_persons(): void {
        $this->postAsAdmin('customers.store', [
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

    public function test_store_persists_bank_details_and_normalizes_iban_bic(): void {
        $this->postAsAdmin('customers.store', [
            'name' => 'Bankkunde',
            'currency' => 'EUR',
            'bank_account_holder' => 'Max Beispiel',
            'bank_iban' => ' de89 3704 0044 0532 0130 00 ', // mit Leerzeichen und Lowercase
            'bank_bic' => 'cobadeffxxx',
            'bank_name' => 'Commerzbank',
        ])
            ->assertRedirect();

        // Der Bankname ist kein Geheimnis und bleibt im Klartext.
        $this->assertDatabaseHas('customers', [
            'name' => 'Bankkunde',
            'bank_name' => 'Commerzbank',
        ]);

        // Kontoinhaber, IBAN und BIC liegen at-rest verschlüsselt
        // (Sicherheitsscan S-21) — geprüft wird über das Modell, dass die
        // Normalisierung greift, und über die Rohzeile, dass nichts im
        // Klartext in der Spalte steht.
        $customer = \App\Models\Customer::query()->where('name', 'Bankkunde')->firstOrFail();

        $this->assertSame('Max Beispiel', $customer->bank_account_holder);
        $this->assertSame('DE89370400440532013000', $customer->bank_iban);
        $this->assertSame('COBADEFFXXX', $customer->bank_bic);

        $raw = \Illuminate\Support\Facades\DB::table('customers')->where('id', $customer->id)->first();

        $this->assertNotSame('DE89370400440532013000', (string) $raw->bank_iban);
        $this->assertNotSame('COBADEFFXXX', (string) $raw->bank_bic);
        $this->assertNotSame('Max Beispiel', (string) $raw->bank_account_holder);
    }

    public function test_store_rejects_invalid_iban(): void {
        $this->postAsAdmin('customers.store', [
            'name' => 'Bankkunde',
            'currency' => 'EUR',
            'bank_iban' => 'NOT-AN-IBAN',
        ])
            ->assertSessionHasErrors('bank_iban');
    }

    public function test_bank_details_helper_returns_has_any_flag(): void {
        $withBank = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'bank_iban' => 'DE89370400440532013000',
        ]);
        $withoutBank = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($withBank->bankDetails()['has_any']);
        $this->assertFalse($withoutBank->bankDetails()['has_any']);
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

        $this->deleteAsAdmin('customers.destroy', $customer)
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

        $this->deleteAsAdmin('customers.destroy', $customer)
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_destroy_succeeds_when_clean(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);

        $this->deleteAsAdmin('customers.destroy', $customer)
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

        $response = $this->getAsUser('customers.export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $this->getStreamContent($response->baseResponse);
        $this->assertStringContainsString('Nummer;Name;', $body);
        // Header + 3 Zeilen → mindestens 4 Newlines
        $this->assertGreaterThanOrEqual(4, substr_count($body, "\n"));
    }

    public function test_audit_log_is_written_on_create_and_update(): void {
        $this->postAsAdmin('customers.store', ['name' => 'Audit Co.', 'currency' => 'EUR'])
            ->assertRedirect();

        $customer = Customer::where('name', 'Audit Co.')->firstOrFail();
        // Genau EINE Zeile pro Event: Trait ist der einzige Schreibpfad (A1, kein Observer-Doppel).
        $this->assertSame(1, $this->auditCount($customer, 'created'));

        $this->putAsAdmin('customers.update', $customer, [
            'name' => 'Audit Co. (geändert)',
            'currency' => 'EUR',
        ])
            ->assertRedirect();

        $this->assertSame(1, $this->auditCount($customer, 'updated'));
    }

    public function test_archive_and_restore_write_exactly_one_audit_row_each(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);

        $this->postAsAdmin('customers.archive', [], $customer)->assertRedirect();
        $this->postAsAdmin('customers.restore', [], $customer)->assertRedirect();

        // archived_at-Wechsel wird als eigenes Event geloggt (GoBD), nicht als `updated`.
        $this->assertSame(1, $this->auditCount($customer, 'archived'));
        $this->assertSame(1, $this->auditCount($customer, 'restored'));
        $this->assertSame(0, $this->auditCount($customer, 'updated'));
    }

    private function auditCount(Customer $customer, string $event): int {
        return AuditLog::query()
            ->where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('event', $event)
            ->count();
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
        ToolkitFile::write($tmp, $csv);
        $upload = new UploadedFile($tmp, 'customers.csv', 'text/csv', null, true);

        $response = $this->postAsAdmin('customers.import', ['file' => $upload]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $existing->id, 'name' => 'Neu Name', 'hourly_rate' => 95.5]);
        $this->assertDatabaseHas('customers', ['number' => 'K-0099', 'name' => 'Frischer Kunde', 'billable' => true]);
    }

    public function test_csv_import_requires_billing_permission(): void {
        $csv = "Nummer;Name\nK-0001;Foo\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        ToolkitFile::write($tmp, $csv);
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

    private function getAsUser(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)->get(route($routeName, $parameters));
    }

    private function deleteAsAdmin(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->delete(route($routeName, $parameters));
    }

    private function postAsAdmin(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->post(route($routeName, $parameters), $payload);
    }

    private function putAsAdmin(string $routeName, mixed $parameters = [], array $payload = []): TestResponse {
        return $this->actingAs($this->admin)->put(route($routeName, $parameters), $payload);
    }

    public function test_customer_show_filters_lexoffice_vouchers_by_global_range(): void {
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);

        \App\Models\LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-in',
            'customer_id' => $customer->id, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-IN-RANGE', 'voucher_date' => '2026-06-15',
            'total_amount' => '100.00', 'currency' => 'EUR', 'archived' => false,
        ]);
        \App\Models\LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-out',
            'customer_id' => $customer->id, 'voucher_type' => 'orderconfirmation', 'voucher_status' => 'open',
            'voucher_number' => 'AB-OUT-RANGE', 'voucher_date' => '2026-01-15',
            'total_amount' => '50.00', 'currency' => 'EUR', 'archived' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession([
                'ui.daterange.preset' => 'custom',
                'ui.daterange.from' => '2026-06-01',
                'ui.daterange.to' => '2026-06-30',
            ])
            ->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('RE-IN-RANGE');
        $response->assertDontSee('AB-OUT-RANGE');
        // Belegbild-Aktion (Vorschau/Download) je Beleg vorhanden.
        $response->assertSee('lexoffice-vouchers', false);
    }
}
