<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentOrderingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Document;

use App\Enums\Document\{DocumentTextFailure, DocumentType};
use App\Enums\Search\SearchSourceType;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\{Customer, Document, DocumentVersionText, Project, SearchDocument, Tag, User};
use App\Services\Document\DocumentService;
use App\Services\Search\Indexing\SearchIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dokumente in der Ordnungsschicht (MVP-819, Feature 155): Schlagwörter und
 * Tätigkeitsindex samt Dateitext.
 *
 * Vorher fiel das Dokument aus beidem heraus — der Schlagwortfilter der
 * Wissenszentrale übersprang den Typ geräuschlos, und der Dateiinhalt war
 * nirgends durchsuchbar.
 */
final class DocumentOrderingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_documents_carry_tags_and_the_hub_filters_by_them(): void {
        $tagged = $this->document('Wartungsvertrag Heizung', ['tags' => 'wartungsvertrag, heizung']);
        $this->document('Lieferschein Mai');

        $this->assertSame(
            ['heizung', 'wartungsvertrag'],
            $tagged->tags->pluck('name')->sort()->values()->all(),
        );

        $tag = Tag::query()->where('name', 'heizung')->firstOrFail();
        $this->actingAs($this->admin)->get(route('knowledge-hub.index', ['tag' => $tag->sqid]))
            ->assertOk()
            ->assertSee('Wartungsvertrag Heizung')
            // Bis MVP-819 fiel hier jedes Dokument heraus, auch das passende.
            ->assertDontSee('Lieferschein Mai');
    }

    public function test_upload_queues_the_text_extraction_only_while_indexing_is_on(): void {
        Queue::fake();

        config(['search.indexing' => false]);
        $this->document('Ohne Index');
        Queue::assertNotPushed(ExtractDocumentTextJob::class);

        config(['search.indexing' => true]);
        $this->document('Mit Index');
        Queue::assertPushed(ExtractDocumentTextJob::class);
    }

    public function test_indexed_document_is_findable_by_file_text(): void {
        config(['search.indexing' => true]);
        Queue::fake();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Müller Haustechnik']);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Dachsanierung',
        ]);
        $document = $this->document('Angebot Dach', [], $project);

        // Was der Job ablegt, wenn die Extraktion geklappt hat.
        DocumentVersionText::query()->create([
            'document_version_id' => (int) $document->currentVersion->id,
            'text' => 'Gerüststellung nach DIN 4420 mit Seriennummer XZ-99182',
            'extracted_at' => now(),
        ]);

        app(SearchIndexer::class)->index(SearchSourceType::Document, (int) $document->id);

        $indexed = SearchDocument::query()->withoutGlobalScopes()
            ->where('source_type', SearchSourceType::Document->value)
            ->where('source_id', $document->id)
            ->firstOrFail();

        $this->assertSame('Angebot Dach', $indexed->title);
        // Die Bezugsachse füllt die Kundenspalten des Index gleich mit.
        $this->assertSame((int) $customer->id, (int) $indexed->customer_id);
        $this->assertSame((int) $project->id, (int) $indexed->project_id);

        $this->actingAs($this->admin)
            ->get(route('search.index', ['q' => 'XZ-99182']))
            ->assertOk()
            ->assertSee('Angebot Dach');
    }

    public function test_personnel_files_stay_out_of_the_index(): void {
        config(['search.indexing' => true]);
        Queue::fake();

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $document = $this->document('Abmahnung', [], $member);

        app(SearchIndexer::class)->index(SearchSourceType::Document, (int) $document->id);

        $this->assertDatabaseMissing('search_documents', [
            'source_type' => SearchSourceType::Document->value,
            'source_id' => $document->id,
        ]);
    }

    public function test_a_failed_extraction_is_recorded_instead_of_retried(): void {
        config(['search.indexing' => true]);

        $document = $this->document('Bild ohne Schrift');
        // Datei entfernen: die Extraktion kann nicht gelingen.
        Storage::disk('local')->delete((string) $document->currentVersion->path);

        (new ExtractDocumentTextJob((int) $document->currentVersion->id))->handle(
            app(\App\Services\Document\DocumentTextExtractor::class),
            app(SearchIndexer::class),
        );

        $record = DocumentVersionText::query()
            ->where('document_version_id', $document->currentVersion->id)
            ->firstOrFail();

        $this->assertNull($record->text);
        $this->assertSame(DocumentTextFailure::VersionMissing, $record->failure_reason);
        $this->assertNotNull($record->extracted_at, 'Der Versuch ist vermerkt — ein zweiter Lauf startet keine neue OCR.');
    }

    public function test_the_document_list_filters_by_tag(): void {
        $this->document('Wartungsvertrag Heizung', ['tags' => 'wartungsvertrag']);
        $this->document('Lieferschein Mai');

        $tag = Tag::query()->where('name', 'wartungsvertrag')->firstOrFail();

        // Bis MVP-821 zeigte die Liste Schlagwörter an, ohne nach ihnen filtern
        // zu können — der Sprung aus dem Einstieg lief dort ins Leere.
        $this->actingAs($this->admin)
            ->get(route('documents.index', ['tag' => $tag->sqid]))
            ->assertOk()
            ->assertSee('Wartungsvertrag Heizung')
            ->assertDontSee('Lieferschein Mai');
    }

    public function test_backfill_queues_only_documents_that_still_lack_their_file_text(): void {
        Queue::fake();

        // Bestand entsteht ohne Extraktion — genau der Zustand, den der Nachlauf aufräumt.
        config(['search.indexing' => false]);
        $pending = $this->document('Altbestand Wartungsplan');
        $done = $this->document('Bereits ausgelesen');
        $this->document('Abmahnung', [], User::factory()->create(['organization_id' => $this->organization->id]));
        DocumentVersionText::query()->create([
            'document_version_id' => (int) $done->currentVersion->id,
            'text' => 'Bereits vorhandener Text',
            'extracted_at' => now(),
        ]);

        config(['search.indexing' => true]);
        $this->artisan('search:extract-texts')->assertSuccessful();

        // Nur der Altbestand: das ausgelesene Dokument hat seinen Eintrag, die
        // Personalakte wird gar nicht indiziert und braucht keine OCR.
        Queue::assertPushed(ExtractDocumentTextJob::class, 1);
        Queue::assertPushed(static fn (ExtractDocumentTextJob $job): bool => $job->documentVersionId === (int) $pending->currentVersion->id);
    }

    public function test_backfill_stops_while_indexing_is_off(): void {
        Queue::fake();

        config(['search.indexing' => false]);
        $this->document('Altbestand ohne Index');

        $this->artisan('search:extract-texts')->assertFailed();
        Queue::assertNothingPushed();
    }

    public function test_backfill_works_in_batches(): void {
        Queue::fake();

        config(['search.indexing' => false]);
        $this->document('Erster Scan');
        $this->document('Zweiter Scan');

        config(['search.indexing' => true]);
        $this->artisan('search:extract-texts', ['--limit' => 1])->assertSuccessful();

        Queue::assertPushed(ExtractDocumentTextJob::class, 1);
    }

    public function test_backfill_retries_only_failures_that_can_change(): void {
        Queue::fake();

        config(['search.indexing' => false]);
        $unsupported = $this->document('Archivdatei');
        $failed = $this->document('Defekter Scan');
        foreach ([[$unsupported, DocumentTextFailure::Unsupported], [$failed, DocumentTextFailure::Failed]] as [$document, $reason]) {
            DocumentVersionText::query()->create([
                'document_version_id' => (int) $document->currentVersion->id,
                'text' => null,
                'extracted_at' => now(),
                'failure_reason' => $reason->value,
            ]);
        }

        config(['search.indexing' => true]);
        $this->artisan('search:extract-texts')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('search:extract-texts', ['--retry-failed' => true])->assertSuccessful();

        // Das Format ändert sich nicht mehr; ein gescheitertes Auslesen kann an
        // einem nachinstallierten Werkzeug gelegen haben.
        Queue::assertPushed(ExtractDocumentTextJob::class, 1);
        Queue::assertPushed(static fn (ExtractDocumentTextJob $job): bool => $job->documentVersionId === (int) $failed->currentVersion->id);
    }

    /** @param array<string, mixed> $attributes */
    private function document(string $title, array $attributes = [], ?object $carrier = null): Document {
        return app(DocumentService::class)->create(
            $carrier,
            $this->admin,
            ['title' => $title, 'document_type' => DocumentType::Other->value] + $attributes,
            UploadedFile::fake()->create('datei.pdf', 20, 'application/pdf'),
        );
    }
}
