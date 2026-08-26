<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentZipImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\Document\DocumentType;
use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Models\{Asset, Customer, Document, ImportRun, Project, User};
use App\Services\Import\DocumentZipImportService;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

class DocumentZipImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1', 'name' => 'Kunde']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'number' => 'P-1']);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'asset_no' => 'AS-1']);
    }

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /** @param array<string, string> $files */
    private function zip(string $manifest, array $files = []): string {
        return ZipFile::createFromStrings(['manifest.csv' => $manifest] + $files);
    }

    public function test_manifest_rows_create_documents_at_customer_project_and_asset(): void {
        $manifest = "file;target_type;target_key;title;document_type;valid_until\n"
            . "vertrag.pdf;customer;K-1;Wartungsvertrag;contract;31.12.2027\n"
            . "docs/plan.txt;project;P-1;Bauplan;other;\n"
            . "handbuch.pdf;asset;AS-1;Handbuch;manual;\n";
        $zip = $this->zip($manifest, [
            'vertrag.pdf' => self::PDF,
            'docs/plan.txt' => "Plan\n",
            'handbuch.pdf' => self::PDF,
        ]);

        $result = app(DocumentZipImportService::class)->importBinary($zip, $this->organization, $this->admin);

        $this->assertSame(['created' => 3, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []], $result);
        $contract = Document::query()->where('title', 'Wartungsvertrag')->firstOrFail();
        $this->assertSame($this->customer->getMorphClass(), $contract->documentable_type);
        $this->assertSame($this->customer->id, $contract->documentable_id);
        $this->assertSame(DocumentType::Contract, $contract->document_type);
        $this->assertSame('2027-12-31', $contract->valid_until?->toDateString());
        $this->assertSame('vertrag.pdf', $contract->currentVersion?->original_name);
        $this->assertTrue(Storage::disk('local')->exists((string) $contract->currentVersion?->path));
        $this->assertSame($this->project->id, Document::query()->where('title', 'Bauplan')->value('documentable_id'));
        $this->assertSame($this->asset->id, Document::query()->where('title', 'Handbuch')->value('documentable_id'));

        // Wiederholimport: gleiche Datei → übersprungen, geänderte Datei → neue Version.
        $again = $this->zip($manifest, [
            'vertrag.pdf' => self::PDF,
            'docs/plan.txt' => "Plan v2 mit mehr Inhalt\n",
            'handbuch.pdf' => self::PDF,
        ]);
        $result = app(DocumentZipImportService::class)->importBinary($again, $this->organization, $this->admin);
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 2, 'failed' => 0], array_diff_key($result, ['errors' => true]));
        $this->assertSame(3, Document::query()->count());
        $this->assertSame(2, Document::query()->where('title', 'Bauplan')->firstOrFail()->versions()->count());
    }

    public function test_unknown_target_missing_file_and_forbidden_extension_are_row_errors(): void {
        $manifest = "file;target_type;target_key;title\n"
            . "ok.pdf;customer;K-1;Gut\n"
            . "ok.pdf;customer;K-404;Kein Kunde\n"
            . "fehlt.pdf;project;P-1;Datei fehlt\n"
            . "script.exe;asset;AS-1;Böse Endung\n"
            . "ok.pdf;user;K-1;Falscher Zieltyp\n";
        $zip = $this->zip($manifest, ['ok.pdf' => self::PDF, 'script.exe' => 'MZ']);

        $result = app(DocumentZipImportService::class)->importBinary($zip, $this->organization, $this->admin);

        $this->assertSame(1, $result['created']);
        $this->assertSame(4, $result['skipped']);
        $this->assertCount(4, $result['errors']);
        $this->assertStringContainsString('Zeile 2', $result['errors'][0]);
        $this->assertStringContainsString('K-404', $result['errors'][0]);
        $this->assertStringContainsString('fehlt.pdf', $result['errors'][1]);
        $this->assertStringContainsString('exe', $result['errors'][2]);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_mime_mismatch_is_row_error(): void {
        $manifest = "file;target_type;target_key;title\nbild.png;customer;K-1;Kein Bild\n";
        // ELF-Kopf: Endung erlaubt, Inhalt (application/octet-stream) nicht.
        $zip = $this->zip($manifest, ['bild.png' => "\x7fELF\x02\x01\x01" . str_repeat("\0", 60)]);

        $result = app(DocumentZipImportService::class)->importBinary($zip, $this->organization, $this->admin);

        $this->assertSame(1, $result['failed'], json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame(0, Document::query()->count());
    }

    public function test_traversal_entry_rejects_the_whole_archive(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'wd_zip_');
        $archive = new ZipArchive();
        $archive->open((string) $tmp, ZipArchive::OVERWRITE);
        $archive->addFromString('manifest.csv', "file;target_type;target_key;title\nok.pdf;customer;K-1;Gut\n");
        $archive->addFromString('ok.pdf', self::PDF);
        $archive->addFromString('../evil.txt', 'x');
        $archive->close();
        $zip = (string) file_get_contents((string) $tmp);
        @unlink((string) $tmp);

        $result = app(DocumentZipImportService::class)->importBinary($zip, $this->organization, $this->admin);

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['created']);
        $this->assertStringContainsString('evil.txt', $result['errors'][0]);
        $this->assertSame(0, Document::query()->count());
    }

    public function test_missing_manifest_and_targets_of_other_org_fail(): void {
        $result = app(DocumentZipImportService::class)->importBinary(ZipFile::createFromStrings(['a.pdf' => self::PDF]), $this->organization, $this->admin);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('manifest.csv', $result['errors'][0]);

        $foreign = \App\Models\Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $foreign->id, 'number' => 'K-F', 'name' => 'Fremd']);
        $zip = $this->zip("file;target_type;target_key;title\nok.pdf;customer;K-F;Fremd\n", ['ok.pdf' => self::PDF]);
        $result = app(DocumentZipImportService::class)->importBinary($zip, $this->organization, $this->admin);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Document::query()->count());
    }

    public function test_wizard_accepts_zip_for_documents_and_confirm_imports(): void {
        $zip = $this->zip("file;target_type;target_key;title\nvertrag.pdf;customer;K-1;Wartungsvertrag\nfehlt.pdf;customer;K-1;Fehlt\n", ['vertrag.pdf' => self::PDF]);

        $this->actingAs($this->admin)
            ->get(route('admin.imports.create', ['entity' => 'documents']))
            ->assertOk()
            ->assertSee('accept=".zip"', false)
            ->assertSee(__('import.entity.documents'));

        $this->actingAs($this->admin)
            ->get(route('admin.imports.template', ['entity' => 'documents']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="manifest.csv"');

        // CSV für Dokumente → Validierungsfehler (nur ZIP erlaubt).
        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => 'documents',
            'file' => UploadedFile::fake()->createWithContent('docs.csv', "file;target_type;target_key;title\n"),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => 'documents',
            'file' => UploadedFile::fake()->createWithContent('docs.zip', $zip),
        ])->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportEntity::Documents, $run->entity);
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(2, $run->rows_total);
        $this->assertSame(1, $run->rows_failed);
        $this->assertSame(1, $run->errors()->where('row_number', 2)->where('field', 'file')->count());

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();
        $run->refresh();
        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(1, $run->rows_created);
        $this->assertSame(1, Document::query()->where('title', 'Wartungsvertrag')->count());
    }
}
