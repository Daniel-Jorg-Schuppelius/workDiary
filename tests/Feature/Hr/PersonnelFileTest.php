<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Enums\Hr\HrDocumentCategory;
use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Models\{AuditLog, Document, Organization, User};
use App\Models\Privacy\{PrivacyAttachment, RetentionProposal};
use App\Services\Hr\PersonnelFilePermissions;
use App\Services\Org\UserOffboardingService;
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, SubjectDataExporter, UserAnonymizationService};
use App\Services\Privacy\Retention\RetentionScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Digitale Personalakte (Feature 141, MVP-708 — Vollscan H7): eigener
 * hrFile-Zugriffskreis ohne Admin-Bypass, Eigenauskunft read-only,
 * Vertraulichkeit erzwungen, Aufbewahrung ab Austritt, Retention-Scan,
 * DSGVO-Auskunft und Audit-Events inkl. Download.
 */
final class PersonnelFileTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $hr;

    private User $member;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');

        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);

        $this->member = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Erika Beispiel']);
        $this->hr = User::factory()->user()->create(['organization_id' => $this->org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->hr->assignRole(PersonnelFilePermissions::ROLE_PERSONALAKTE);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function upload(User $actor, User $member, array $overrides = []): TestResponse {
        return $this->actingAs($actor)->post(route('org.members.personnel-file.store', $member), $overrides + [
            'title' => 'Arbeitsvertrag 2026',
            'hr_category' => HrDocumentCategory::Contract->value,
            'file' => UploadedFile::fake()->create('vertrag.pdf', 40, 'application/pdf'),
        ]);
    }

    private function file(): Document {
        $this->upload($this->hr, $this->member)->assertRedirect();

        return Document::query()->personnelFilesOf($this->member)->firstOrFail();
    }

    public function test_hr_circle_sees_uploads_and_downloads_with_audit(): void {
        $this->actingAs($this->hr)->get(route('org.members.personnel-file.index', $this->member))
            ->assertOk()
            ->assertSee(route('org.members.personnel-file.create', $this->member));

        $document = $this->file();
        $this->assertTrue($document->isPersonnelFile());
        $this->assertTrue($document->confidential);
        $this->assertSame(HrDocumentCategory::Contract, $document->hr_category);
        $this->assertSame($this->org->id, (int) $document->organization_id);

        $this->actingAs($this->hr)->get(route('org.members.personnel-file.index', $this->member))
            ->assertOk()
            ->assertSee('Arbeitsvertrag 2026');
        $this->actingAs($this->hr)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($this->hr)->get(route('documents.download', $document))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.created', 'auditable_id' => $document->id, 'user_id' => $this->hr->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.downloaded', 'auditable_id' => $document->id, 'user_id' => $this->hr->id]);
    }

    public function test_other_member_is_forbidden(): void {
        $document = $this->file();
        $colleague = User::factory()->user()->create(['organization_id' => $this->org->id]);

        $this->actingAs($colleague)->get(route('org.members.personnel-file.index', $this->member))->assertForbidden();
        $this->actingAs($colleague)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($colleague)->get(route('documents.download', $document))->assertForbidden();
        $this->upload($colleague, $this->member, ['title' => 'Einschleusung'])->assertForbidden();
        $this->assertSame(1, Document::query()->personnelFilesOf($this->member)->count());
    }

    public function test_org_admin_without_hr_permission_is_forbidden_and_index_hides_the_file(): void {
        $document = $this->file();
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->assertTrue($admin->isAdmin());

        $this->actingAs($admin)->get(route('org.members.personnel-file.index', $this->member))->assertForbidden();
        $this->actingAs($admin)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($admin)->get(route('documents.download', $document))->assertForbidden();
        $this->actingAs($admin)->delete(route('documents.destroy', $document))->assertForbidden();
        $this->upload($admin, $this->member)->assertForbidden();

        // Die Dokument-Übersicht leakt die Akte nicht — auch nicht an Admins.
        $this->actingAs($admin)->get(route('documents.index'))->assertOk()->assertDontSee('Arbeitsvertrag 2026');
        $this->actingAs($admin)->get(route('documents.index', ['ref' => 'user']))->assertOk()->assertDontSee('Arbeitsvertrag 2026');
        // Der hrFile-Kreis sieht sie dort (Vertraulichkeit greift für ihn nicht).
        $this->actingAs($this->hr)->get(route('documents.index'))->assertOk()->assertSee('Arbeitsvertrag 2026');
        // Mitgliederliste: Akten-Button nur für den Kreis.
        $this->actingAs($admin)->get(route('org.members.index'))->assertOk()
            ->assertDontSee(route('org.members.personnel-file.index', $this->member));
        $this->assertNotNull(Document::query()->find($document->id));
    }

    public function test_subject_reads_own_file_read_only(): void {
        $document = $this->file();

        $this->actingAs($this->member)->get(route('account.personnel-file'))
            ->assertOk()
            ->assertSee('Arbeitsvertrag 2026')
            ->assertDontSee(route('org.members.personnel-file.create', $this->member))
            ->assertDontSee(route('personnel-file.edit', $document));
        $this->actingAs($this->member)->get(route('org.members.personnel-file.index', $this->member))->assertOk();
        $this->actingAs($this->member)->get(route('documents.download', $document))->assertOk();
        $this->actingAs($this->member)->get(route('documents.versions', $document))->assertOk();

        // Kein Schreiben: Upload, Metadaten, Version, Löschen, Kundenfreigabe.
        $this->upload($this->member, $this->member)->assertForbidden();
        $this->actingAs($this->member)->put(route('personnel-file.update', $document), [
            'title' => 'Manipuliert', 'hr_category' => HrDocumentCategory::Other->value,
        ])->assertForbidden();
        $this->actingAs($this->member)->post(route('documents.versions.store', $document), [
            'file' => UploadedFile::fake()->create('neu.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($this->member)->delete(route('documents.destroy', $document))->assertForbidden();
        $this->actingAs($this->member)->post(route('documents.customer-release', $document))->assertForbidden();
        $this->assertSame('Arbeitsvertrag 2026', $document->fresh()?->title);

        // Eigenzugriff ist auditiert (Download), aber kein „Fremdzugriff".
        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.downloaded', 'user_id' => $this->member->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'document.confidentialAccessed', 'user_id' => $this->member->id]);
        // Übersicht: die eigene Akte taucht nur unter „Mein Konto" auf.
        $this->actingAs($this->member)->get(route('documents.index'))->assertOk()->assertDontSee('Arbeitsvertrag 2026');
    }

    public function test_confidential_is_enforced_and_release_is_impossible(): void {
        $this->upload($this->hr, $this->member, ['confidential' => 0])->assertRedirect();
        $document = Document::query()->personnelFilesOf($this->member)->firstOrFail();
        $this->assertTrue($document->confidential);

        $this->actingAs($this->hr)->put(route('personnel-file.update', $document), [
            'title' => 'Arbeitsvertrag 2026 (geändert)',
            'hr_category' => HrDocumentCategory::Amendment->value,
            'confidential' => 0,
        ])->assertRedirect();
        $document->refresh();
        $this->assertTrue($document->confidential);
        $this->assertSame(HrDocumentCategory::Amendment, $document->hr_category);
        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.updated', 'auditable_id' => $document->id]);

        // Auch die allgemeine Dokument-Route erzwingt das Merkmal.
        $this->actingAs($this->hr)->put(route('documents.update', $document), [
            'title' => 'Arbeitsvertrag 2026 (Route)',
            'hr_category' => HrDocumentCategory::Contract->value,
            'confidential' => 0,
        ])->assertRedirect();
        $this->assertTrue($document->fresh()?->confidential);

        // Kundenfreigabe ist für Personalakten ausgeschlossen — auch für den Kreis.
        $this->actingAs($this->hr)->post(route('documents.customer-release', $document))->assertForbidden();

        // Manuelle Vernichtung durch den Kreis: Datei weg, Audit bleibt.
        $path = (string) $document->currentVersion?->path;
        Storage::disk('local')->assertExists($path);
        $this->actingAs($this->hr)->delete(route('documents.destroy', $document))->assertRedirect();
        $this->assertNull(Document::query()->withTrashed()->find($document->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'hrFile.deleted', 'auditable_id' => $document->id]);
    }

    public function test_retention_is_set_on_exit_and_for_already_left_members(): void {
        $contract = $this->file();
        $this->assertNull($contract->retention_until, 'Ohne Austritt kein Aufbewahrungsende.');

        $leftAt = now()->subDays(3)->toImmutable();
        app(UserOffboardingService::class)->initiate($this->member, $leftAt, $this->hr);

        $contract->refresh();
        $this->assertSame($leftAt->addYears(3)->toDateString(), $contract->retention_until?->toDateString());

        // Upload für bereits Ausgetretene: Frist sofort aus left_at + Kategorie.
        $this->upload($this->hr, $this->member, ['title' => 'Abmahnung 2024', 'hr_category' => HrDocumentCategory::Warning->value])->assertRedirect();
        $warning = Document::query()->personnelFilesOf($this->member)->where('title', 'Abmahnung 2024')->firstOrFail();
        $this->assertSame($leftAt->addYears(2)->toDateString(), $warning->retention_until?->toDateString());

        $this->upload($this->hr, $this->member, ['title' => 'Lohnsteuerbescheinigung', 'hr_category' => HrDocumentCategory::PayrollReference->value])->assertRedirect();
        $payroll = Document::query()->personnelFilesOf($this->member)->where('title', 'Lohnsteuerbescheinigung')->firstOrFail();
        $this->assertSame($leftAt->addYears(6)->toDateString(), $payroll->retention_until?->toDateString());
    }

    public function test_retention_scan_lists_due_files_and_purge_destroys_them(): void {
        $document = $this->file();
        $path = (string) $document->currentVersion?->path;
        $this->member->forceFill([
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ])->save();
        $document->forceFill(['retention_until' => now()->subDay()->toDateString()])->save();

        $result = app(RetentionScanService::class)->scan($this->org);

        $proposal = RetentionProposal::query()->where('area', 'personnel_files')->firstOrFail();
        $this->assertSame($document->id, (int) $proposal->subject_id);
        $this->assertSame(RetentionProposal::STATUS_PENDING, $proposal->status);
        // Die Akte blockt die Anonymisierung des Kontos (Bereichs-Muster „zuerst bereinigen").
        $this->assertSame(0, RetentionProposal::query()->where('area', 'employee_records')->count());
        $this->assertGreaterThanOrEqual(1, $result['exempt']);
        $this->assertNotNull(Document::query()->find($document->id), 'Der Scan schlägt nur vor.');

        try {
            app(UserAnonymizationService::class)->anonymize($this->member, $this->hr);
            $this->fail('Anonymisierung muss bei offener Personalakte blockieren.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Personalakte', $e->getMessage());
        }

        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $service = app(RetentionScanService::class);
        $service->approve($proposal, $admin);
        $this->assertNotNull(Document::query()->find($document->id), 'approve vernichtet noch nicht.');
        $service->purge($proposal->fresh(), $admin);

        $this->assertNull(Document::query()->withTrashed()->find($document->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, AuditLog::query()->where('event', 'hrFile.deleted')->where('auditable_id', $document->id)->count());

        // Danach ist das Konto anonymisierbar (nächster Scan).
        app(RetentionScanService::class)->scan($this->org);
        $this->assertSame(1, RetentionProposal::query()->where('area', 'employee_records')->count());
    }

    public function test_subject_export_contains_the_personnel_file_with_files(): void {
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        // Echter Inhalt: UploadedFile::fake()->create() meldet nur eine Größe, schreibt aber keine Bytes.
        $content = "%PDF-1.4\n%workDiary Personalakte\n";
        $this->upload($this->hr, $this->member, [
            'file' => UploadedFile::fake()->createWithContent('vertrag.pdf', $content),
        ])->assertRedirect();
        $document = Document::query()->personnelFilesOf($this->member)->firstOrFail();

        DataProtectionPermissions::seedOrganization($this->org);
        $officer = User::factory()->create(['organization_id' => $this->org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $dsr = app(DataSubjectRequestService::class)->open(
            $this->org, DataSubjectRequestType::Access, 'Erika Beispiel', 'Bitte Auskunft nach Art. 15.', null, $officer,
        );

        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::User, $this->member);

        $section = collect($payload['sections'])->firstWhere('key', 'personnel_file');
        $this->assertNotNull($section, 'Abschnitt Personalakte fehlt.');
        $this->assertFalse($section['portable']);
        $rows = array_values($section['lists'])[0];
        $this->assertSame('Arbeitsvertrag 2026', $rows[0]['title']);
        $this->assertSame('vertrag.pdf', $rows[0]['file']);
        $this->assertSame(1, collect($section['families'])->firstWhere('table', 'documents')['count']);

        $attachments = $exporter->attachFiles($dsr, DataSubjectKind::User, $payload, $officer, $this->member);
        $names = array_map(static fn(PrivacyAttachment $a): string => $a->filename, $attachments);
        $this->assertCount(4, $attachments);
        $this->assertContains('personalakte-' . $dsr->request_number . '-01-vertrag.pdf', $names);
        $this->assertSame(strlen($content), (int) end($attachments)->size);
        $this->assertSame(strlen($content), (int) $document->currentVersion?->size);
    }
}
