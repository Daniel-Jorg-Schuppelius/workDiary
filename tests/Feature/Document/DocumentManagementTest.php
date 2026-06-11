<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Document;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Customer, Document, DocumentVersion, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_user_can_store_document_with_upload(): void {
        $user = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('vertrag.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'title' => 'Wartungsvertrag Halle 3',
                'document_type' => DocumentType::Contract->value,
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addYear()->toDateString(),
                'description' => 'Jährliche Wartung inkl. Prüfprotokoll.',
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'title' => 'Wartungsvertrag Halle 3',
            'document_type' => DocumentType::Contract->value,
            'status' => DocumentStatus::Active->value,
            'created_by_user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        $document = Document::query()->firstOrFail();
        $version = $document->currentVersion;
        $this->assertNotNull($version);
        $this->assertSame(1, $version->version_no);
        $this->assertSame('vertrag.pdf', $version->original_name);
        $this->assertTrue(Storage::disk('local')->exists($version->path));
    }

    public function test_store_with_customer_reference_links_documentable(): void {
        $user = User::factory()->user()->create();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $file = UploadedFile::fake()->create('zertifikat.pdf', 50, 'application/pdf');

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'title' => 'Zertifikat Brandschutz',
                'document_type' => DocumentType::Certificate->value,
                'documentable_kind' => 'customer',
                'documentable_id' => Sqid::encode(Customer::class, $customer->id),
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'title' => 'Zertifikat Brandschutz',
            'documentable_type' => Customer::class,
            'documentable_id' => $customer->id,
        ]);
    }

    public function test_store_rejects_disallowed_file_type(): void {
        $user = User::factory()->user()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $this->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'title' => 'Böse Datei',
                'document_type' => DocumentType::Other->value,
                'file' => $file,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::query()->count());
    }

    public function test_user_without_create_permission_cannot_store(): void {
        // Geschäftsführung hat nur viewAny/view, kein document.create.
        $author = User::factory()->user()->create();
        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $author->organization_id]);

        $this->actingAs($gf)
            ->post(route('documents.store'), [
                'title' => 'Verboten',
                'document_type' => DocumentType::Other->value,
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_add_version_increments_version_no_and_sets_current(): void {
        $user = User::factory()->user()->create();
        $document = $this->makeDocumentFor($user);
        $first = $this->attachVersion($document, $user, 1);

        $this->actingAs($user)
            ->post(route('documents.versions.store', $document), [
                'file' => UploadedFile::fake()->create('vertrag-v2.pdf', 80, 'application/pdf'),
                'note' => 'Preisanpassung 2027',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->versions()->count());
        $current = $document->currentVersion;
        $this->assertNotNull($current);
        $this->assertSame(2, $current->version_no);
        $this->assertNotSame($first->id, $current->id);
        $this->assertSame('Preisanpassung 2027', $current->note);
        $this->assertSame('vertrag-v2.pdf', $current->original_name);
    }

    public function test_update_changes_metadata(): void {
        $user = User::factory()->user()->create();
        $document = $this->makeDocumentFor($user);

        $this->actingAs($user)
            ->put(route('documents.update', $document), [
                'title' => 'Neuer Titel',
                'document_type' => DocumentType::Insurance->value,
                'valid_from' => '2026-01-01',
                'valid_until' => '2027-12-31',
                'description' => 'Aktualisierte Beschreibung',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('Neuer Titel', $document->title);
        $this->assertSame(DocumentType::Insurance, $document->document_type);
        $this->assertSame('2027-12-31', $document->valid_until?->toDateString());
        $this->assertSame('Aktualisierte Beschreibung', $document->description);
    }

    public function test_plain_user_cannot_update_foreign_document(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $document = $this->makeDocumentFor($author);

        $this->actingAs($other)
            ->put(route('documents.update', $document), [
                'title' => 'Übernahme',
                'document_type' => DocumentType::Other->value,
            ])
            ->assertForbidden();
    }

    public function test_teamleitung_can_update_foreign_document_but_not_delete(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $document = $this->makeDocumentFor($author);

        $this->actingAs($lead)
            ->put(route('documents.update', $document), [
                'title' => 'Korrigierter Titel',
                'document_type' => DocumentType::Contract->value,
            ])
            ->assertRedirect();

        $this->actingAs($lead)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    }

    public function test_download_current_and_specific_version(): void {
        $user = User::factory()->user()->create();
        $document = $this->makeDocumentFor($user);
        $v1 = $this->attachVersion($document, $user, 1);
        $v2 = $this->attachVersion($document, $user, 2);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload($v2->original_name);

        $this->actingAs($user)
            ->get(route('documents.download', ['document' => $document, 'version' => $v1]))
            ->assertOk()
            ->assertDownload($v1->original_name);
    }

    public function test_download_requires_view_permission(): void {
        $author = User::factory()->user()->create();
        // Personalverwaltung hat keinerlei document.*-Permissions.
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $author->organization_id]);
        $document = $this->makeDocumentFor($author);
        $this->attachVersion($document, $author, 1);

        $this->actingAs($hr)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_download_cross_organization_is_not_found(): void {
        $author = User::factory()->user()->create();
        $stranger = User::factory()->user()->create(); // eigene Organisation
        $document = $this->makeDocumentFor($author);
        $this->attachVersion($document, $author, 1);

        $this->actingAs($stranger)
            ->get(route('documents.download', $document))
            ->assertNotFound();
    }

    public function test_version_of_other_document_is_rejected(): void {
        $user = User::factory()->user()->create();
        $documentA = $this->makeDocumentFor($user);
        $documentB = $this->makeDocumentFor($user);
        $this->attachVersion($documentA, $user, 1);
        $foreignVersion = $this->attachVersion($documentB, $user, 1);

        $this->actingAs($user)
            ->get(route('documents.download', ['document' => $documentA, 'version' => $foreignVersion]))
            ->assertNotFound();
    }

    public function test_expiring_within_and_expired_scopes(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $expired = Document::factory()->expired()->create(['created_by_user_id' => $user->id]);
        $soon = Document::factory()->expiringInDays(14)->create(['created_by_user_id' => $user->id]);
        $later = Document::factory()->expiringInDays(120)->create(['created_by_user_id' => $user->id]);
        $unlimited = Document::factory()->create(['created_by_user_id' => $user->id]);
        // Archivierte fallen aus beiden Scopes heraus.
        $archivedExpired = Document::factory()->expired()->archived()->create(['created_by_user_id' => $user->id]);

        $expiringIds = Document::query()->expiringWithin(30)->pluck('id')->all();
        $this->assertSame([$soon->id], $expiringIds);

        $expiredIds = Document::query()->expired()->pluck('id')->all();
        $this->assertSame([$expired->id], $expiredIds);

        $activeIds = Document::query()->active()->orderBy('id')->pluck('id')->all();
        $this->assertSame([$soon->id, $later->id, $unlimited->id], $activeIds);

        $this->assertTrue($expired->isExpired());
        $this->assertSame(DocumentStatus::Expired, $expired->effectiveStatus());
        $this->assertSame(DocumentStatus::Archived, $archivedExpired->effectiveStatus());
        $this->assertSame(DocumentStatus::Active, $soon->effectiveStatus());
    }

    public function test_archive_sets_status_and_requires_permission(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $document = $this->makeDocumentFor($author);

        // user-Rolle hat kein document.archive.
        $this->actingAs($author)
            ->post(route('documents.archive', $document))
            ->assertForbidden();

        $this->actingAs($lead)
            ->post(route('documents.archive', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Archived, $document->status);
    }

    public function test_admin_can_delete_document(): void {
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $document = $this->makeDocumentFor($author);

        $this->actingAs($admin)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_index_lists_documents_and_filters_by_type(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);
        Document::factory()->certificate()->create([
            'title' => 'TÜV-Zertifikat Aufzug',
            'created_by_user_id' => $user->id,
        ]);
        Document::factory()->create([
            'title' => 'Datenblatt Pumpe',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index', ['type' => DocumentType::Certificate->value]))
            ->assertOk()
            ->assertSee('TÜV-Zertifikat Aufzug')
            ->assertDontSee('Datenblatt Pumpe');
    }

    public function test_guest_cannot_access_documents(): void {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    /** Dokument der Organisation des Users (ohne Versionen). */
    private function makeDocumentFor(User $creator): Document {
        return Document::factory()->create([
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
        ]);
    }

    /** Persistiert eine Version inkl. Fake-Datei und setzt den current-Zeiger. */
    private function attachVersion(Document $document, User $uploader, int $no): DocumentVersion {
        $path = 'documents/' . now()->format('Y/m') . '/' . \Illuminate\Support\Str::uuid()->toString() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test ' . $no);

        $version = $document->versions()->create([
            'version_no' => $no,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'datei-v' . $no . '.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'uploaded_by_user_id' => $uploader->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }
}
