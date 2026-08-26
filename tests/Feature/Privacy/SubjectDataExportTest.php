<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectDataExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Models\Applications\JobApplication;
use App\Models\{AuditLog, ContactAddress, ContactBankAccount, Customer, Lead, Organization, SickLeave, Supplier, TimeEntry, User, Vacation};
use App\Models\Location\LocationPoint;
use App\Models\Privacy\{DataSubjectRequest, PrivacyAttachment};
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, SubjectDataExporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * DSGVO-Auskunft mit echten Betroffenendaten (Feature 129, Vollscan H2):
 * Struktur je Betroffenenart (Stammdaten-Vollausgabe + Familien-Zähler),
 * PII-Entschlüsselung, Art.-20-CSV nur Stammdaten, PDF-Rendering,
 * DEK-verschlüsselte Fall-Ablage inkl. Download/Shredding und harte
 * Mandantentrennung.
 */
class SubjectDataExportTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    private function dsr(Organization $org, User $officer): DataSubjectRequest {
        return app(DataSubjectRequestService::class)->open(
            $org,
            DataSubjectRequestType::Access,
            'Max Mustermann',
            'Bitte Auskunft nach Art. 15.',
            null,
            $officer,
        );
    }

    /** @param array<string, mixed> $payload */
    private function section(array $payload, string $key): array {
        foreach ($payload['sections'] as $section) {
            if ($section['key'] === $key) {
                return $section;
            }
        }
        $this->fail("Abschnitt {$key} fehlt in der Auskunft.");
    }

    /** @param array<string, mixed> $section */
    private function familyCount(array $section, string $table): int {
        foreach ($section['families'] as $family) {
            if ($family['table'] === $table) {
                return (int) $family['count'];
            }
        }
        $this->fail("Familie {$table} fehlt im Abschnitt {$section['key']}.");
    }

    // ── Struktur je Betroffenenart ──────────────────────────────────────────

    public function test_user_export_contains_decrypted_pii_and_family_counters(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);

        $employee = User::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Erika Beispiel',
            'tax_identification_number' => '12 345 678 901',
            'social_security_number' => '65 170839 J 003',
            'phone' => '+49 30 123456',
        ]);
        TimeEntry::factory()->administration()->count(2)->create(['organization_id' => $org->id, 'user_id' => $employee->id]);
        Vacation::create([
            'organization_id' => $org->id, 'user_id' => $employee->id,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-05',
            'type' => 'vacation', 'status' => 'approved',
        ]);
        SickLeave::factory()->create(['organization_id' => $org->id, 'user_id' => $employee->id]);
        LocationPoint::create([
            'organization_id' => $org->id, 'user_id' => $employee->id,
            'recorded_at' => '2026-08-01 08:00:00', 'lat' => '52.5200000', 'lng' => '13.4050000', 'source' => 'owntracks',
        ]);
        AuditLog::create([
            'organization_id' => $org->id, 'user_id' => $officer->id, 'event' => 'user.updated',
            'auditable_type' => User::class, 'auditable_id' => $employee->id, 'changes' => [],
        ]);

        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::User, $employee);

        $master = $this->section($payload, 'master_data');
        $this->assertSame('12 345 678 901', $master['fields']['tax_identification_number']['value'], 'PII muss entschlüsselt vorliegen.');
        $this->assertSame('65 170839 J 003', $master['fields']['social_security_number']['value']);
        $this->assertSame('Erika Beispiel', $master['fields']['name']['value']);
        $this->assertTrue($master['portable']);

        $work = $this->section($payload, 'work_time');
        $this->assertSame(2, $this->familyCount($work, 'time_entries'));
        $this->assertSame(1, $this->familyCount($work, 'vacations'));
        $this->assertSame(1, $this->familyCount($work, 'sick_leaves'));

        // Standortdaten: Zähler + Zeitraum, keine Koordinaten in der Auskunft.
        $location = $this->section($payload, 'location_data');
        $this->assertSame(1, $this->familyCount($location, 'location_points'));
        $this->assertSame('2026-08-01', $location['families'][0]['from']);
        $this->assertStringNotContainsString('52.52', $exporter->toJson($payload));

        // Mindestens der manuell angelegte Eintrag; die Anlage des Users selbst
        // kann (Auditable) weitere Ereignisse über die Person erzeugen.
        $audit = $this->section($payload, 'audit_trail');
        $this->assertGreaterThanOrEqual(1, $this->familyCount($audit, 'audit_logs_about'));
    }

    public function test_portal_user_export_has_reduced_master_data(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);

        $customer = Customer::factory()->create(['organization_id' => $org->id, 'name' => 'Muster GmbH']);
        $portal = User::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'name' => 'Portalo Kunde',
            'social_security_number' => 'SOLL-NICHT-ERSCHEINEN',
        ]);

        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::PortalUser, $portal);

        $master = $this->section($payload, 'master_data');
        $this->assertSame('Muster GmbH', $master['fields']['customer']['value']);
        $this->assertArrayNotHasKey('social_security_number', $master['fields'], 'Portal-Nutzer erhalten keine HR-Felder.');
        $this->section($payload, 'audit_trail');
    }

    public function test_customer_export_contains_contact_details_and_family_counters(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);

        $customer = Customer::factory()->create(['organization_id' => $org->id, 'name' => 'Muster GmbH']);
        ContactAddress::create([
            'organization_id' => $org->id,
            'addressable_type' => $customer->getMorphClass(), 'addressable_id' => $customer->id,
            'kind' => 'billing', 'street' => 'Beispielweg 1', 'zip' => '10115', 'city' => 'Berlin',
            'country_code' => 'DE', 'is_primary' => true,
        ]);
        ContactBankAccount::create([
            'organization_id' => $org->id,
            'accountable_type' => $customer->getMorphClass(), 'accountable_id' => $customer->id,
            'account_holder' => 'Muster GmbH', 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001',
            'bank_name' => 'Testbank', 'is_primary' => true,
        ]);
        \App\Models\CommunicationNote::factory()->create([
            'organization_id' => $org->id,
            'notable_type' => $customer->getMorphClass(), 'notable_id' => $customer->id,
        ]);

        $payload = app(SubjectDataExporter::class)->build($dsr, DataSubjectKind::Customer, $customer);

        $contact = $this->section($payload, 'contact_details');
        $this->assertSame('Beispielweg 1', $contact['fields']['primary_street']['value'], 'contact_*-PII muss entschlüsselt vorliegen.');
        $this->assertSame('DE02120300000000202051', $contact['fields']['primary_iban']['value']);
        $this->assertCount(1, $contact['lists'][__('Adressen')]);

        $this->assertSame(1, $this->familyCount($this->section($payload, 'communication'), 'communication_notes'));
        $documents = $this->section($payload, 'documents');
        $this->assertSame(0, $this->familyCount($documents, 'invoices'));
        $this->assertSame(0, $this->familyCount($documents, 'portal_users'));
    }

    public function test_supplier_lead_and_application_structures(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $exporter = app(SubjectDataExporter::class);

        $supplier = Supplier::factory()->create(['organization_id' => $org->id, 'name' => 'Zuliefer KG']);
        $payload = $exporter->build($dsr, DataSubjectKind::Supplier, $supplier);
        $this->assertSame('Zuliefer KG', $this->section($payload, 'master_data')['fields']['name']['value']);
        $this->assertSame(0, $this->familyCount($this->section($payload, 'documents'), 'purchase_orders'));

        $lead = Lead::create([
            'organization_id' => $org->id, 'company' => 'Interessent AG',
            'contact_name' => 'Ida Interessent', 'email' => 'ida@example.test',
            'source' => 'web', 'status' => 'new',
        ]);
        $payload = $exporter->build($dsr, DataSubjectKind::Lead, $lead);
        $this->assertSame('Ida Interessent', $this->section($payload, 'master_data')['fields']['contact_name']['value']);
        $this->assertSame(0, $this->familyCount($this->section($payload, 'communication'), 'communication_notes'));

        $application = JobApplication::create([
            'organization_id' => $org->id,
            'candidate_name' => 'Bernd Bewerber', 'email' => 'bernd@example.test', 'phone' => '+49 40 999',
            'source' => 'website', 'status' => 'received',
        ]);
        $payload = $exporter->build($dsr, DataSubjectKind::JobApplication, $application);
        $master = $this->section($payload, 'master_data');
        $this->assertSame('Bernd Bewerber', $master['fields']['candidate_name']['value'], 'Bewerber-PII muss entschlüsselt vorliegen.');
        $this->assertSame(0, $this->familyCount($this->section($payload, 'application_records'), 'job_application_documents'));
    }

    // ── Formate ─────────────────────────────────────────────────────────────

    public function test_portability_csv_contains_only_master_data(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $employee = User::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Erika Beispiel',
            'social_security_number' => '65 170839 J 003',
        ]);
        TimeEntry::factory()->administration()->create(['organization_id' => $org->id, 'user_id' => $employee->id]);

        $exporter = app(SubjectDataExporter::class);
        $csv = $exporter->toPortabilityCsv($exporter->build($dsr, DataSubjectKind::User, $employee));

        $this->assertStringContainsString('social_security_number', $csv);
        $this->assertStringContainsString('65 170839 J 003', $csv);
        // Familien-Aggregat gehört NICHT in die Art.-20-CSV.
        $this->assertStringNotContainsString('time_entries', $csv);
        $this->assertCount(2, explode("\r\n", trim($csv)), 'Flache CSV: Kopfzeile + eine Datenzeile.');
    }

    public function test_pdf_renders(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $employee = User::factory()->create(['organization_id' => $org->id]);

        $exporter = app(SubjectDataExporter::class);
        $pdf = $exporter->renderPdf($exporter->build($dsr, DataSubjectKind::User, $employee), $org->id);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    // ── Fall-Anbindung (HTTP): verschlüsselte Ablage, Audit, Download ───────

    public function test_show_page_renders_generate_action_with_pickers(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        Customer::factory()->create(['organization_id' => $org->id, 'name' => 'Muster GmbH']);

        $this->actingAs($officer)
            ->get(route('dataprotection.requests.show', $dsr))
            ->assertOk()
            ->assertSee(__('Auskunft erzeugen (Art. 15/20)'))
            ->assertSee('Muster GmbH')
            ->assertSee(route('dataprotection.requests.subject-export', $dsr), false);
    }

    public function test_generate_attaches_encrypted_files_with_audit_and_event(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $employee = User::factory()->create([
            'organization_id' => $org->id,
            'social_security_number' => '65 170839 J 003',
        ]);

        $this->actingAs($officer)
            ->post(route('dataprotection.requests.subject-export', $dsr), [
                'subject_type' => 'user',
                'subject_id' => $employee->sqid,
            ])
            ->assertRedirect();

        $attachments = PrivacyAttachment::query()
            ->where('attachable_type', $dsr->getMorphClass())
            ->where('attachable_id', $dsr->id)
            ->get();
        $this->assertCount(3, $attachments, 'JSON + PDF + Art.-20-CSV.');

        // At-rest: Dateiinhalt ist Chiffretext, nie Klartext-PII.
        foreach ($attachments as $attachment) {
            $raw = (string) Storage::disk('local')->get($attachment->path);
            $this->assertStringNotContainsString('65 170839 J 003', $raw);
            $this->assertStringStartsWith(SubjectDataExporter::STORAGE_PREFIX, $attachment->path);
        }

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'user_id' => $officer->id,
            'event' => 'privacy.subjectExportGenerated',
        ]);
        $this->assertDatabaseHas('privacy_request_events', [
            'request_id' => $dsr->id,
            'event' => 'subject_export_generated',
        ]);
    }

    public function test_download_decrypts_and_shredding_makes_it_unavailable(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $employee = User::factory()->create([
            'organization_id' => $org->id,
            'social_security_number' => '65 170839 J 003',
        ]);

        $this->actingAs($officer)->post(route('dataprotection.requests.subject-export', $dsr), [
            'subject_type' => 'user',
            'subject_id' => $employee->sqid,
        ])->assertRedirect();

        /** @var PrivacyAttachment $json */
        $json = PrivacyAttachment::query()->where('mime', 'application/json')->firstOrFail();
        $response = $this->actingAs($officer)->get(route('dataprotection.attachment.download', $json));
        $response->assertOk();
        $this->assertStringContainsString('65 170839 J 003', (string) $response->getContent(), 'Download liefert den entschlüsselten Klartext.');

        // Crypto-Shredding des Falls macht auch die Auskunftspakete unlesbar.
        $dsr->refresh()->shredDek();
        $this->actingAs($officer)->get(route('dataprotection.attachment.download', $json))->assertStatus(410);
    }

    // ── Tenancy ─────────────────────────────────────────────────────────────

    public function test_foreign_org_subject_is_never_exportable(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $foreignEmployee = User::factory()->create(['organization_id' => $foreignOrg->id]);

        $this->actingAs($officer)->post(route('dataprotection.requests.subject-export', $dsr), [
            'subject_type' => 'user',
            'subject_id' => $foreignEmployee->sqid,
        ])->assertNotFound();

        $this->assertSame(0, PrivacyAttachment::query()->count());
    }

    public function test_portal_user_kind_rejects_regular_employee(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $dsr = $this->dsr($org, $officer);
        $employee = User::factory()->create(['organization_id' => $org->id, 'customer_id' => null]);

        $this->actingAs($officer)->post(route('dataprotection.requests.subject-export', $dsr), [
            'subject_type' => 'portal_user',
            'subject_id' => $employee->sqid,
        ])->assertNotFound();
    }
}
