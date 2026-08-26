<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiWave3AssistanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Document\DocumentType;
use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Enums\User\Permission;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiTextSuggestion};
use App\Models\{Document, DocumentVersion, ImportRun, User};
use App\Services\Ai\Dto\{ClassifyRequest, ExtractRequest};
use App\Services\Ai\Suggestions\{DocumentMetadataSuggestionService, ImportMappingSuggestionService};
use App\Services\Import\CsvPreflightAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Welle 3 (Feature 148, MVP-732): DMS-Dokumenttyp/Fristen erkennen und
 * Import-Spaltenzuordnung vorschlagen.
 *
 * Beide Einsatzstellen bleiben Vorschläge: Dokument-Chips werden einzeln über
 * den regulären DocumentService übernommen, die Spaltenzuordnung ist ein
 * reiner Hinweis (der HeaderMapper bleibt die verbindliche Zuordnung).
 */
class AiWave3AssistanceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private FakeAiProvider $fake;

    private AiProviderConnection $cloud;

    private AiProviderConnection $local;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['locale' => 'de']);
        Storage::fake('local');
        $this->fake = FakeAiProviderFactory::install();

        $this->admin = $this->orgAdmin();
        $this->admin->givePermissionTo([Permission::AiUse->value]);

        $this->cloud = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->local = AiProviderConnection::factory()->local()->create(['organization_id' => $this->organization->id]);
    }

    private function enable(string $capability, bool $local = false): void {
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => $capability,
            'enabled' => true,
            'allowed_connection_ids' => [$local ? $this->local->id : $this->cloud->id],
        ]);
    }

    // ── (g) dms.classify_extract ─────────────────────────────────────────────

    private function documentWithText(string $text = "Wartungsvertrag\nLaufzeit bis 31.12.2027"): Document {
        $document = Document::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Scan 0815',
            'document_type' => DocumentType::Other->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $path = 'documents/test/' . $document->id . '.txt';
        Storage::disk('local')->put($path, $text);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'wartungsvertrag.txt',
            'mime' => 'text/plain',
            'uploaded_by_user_id' => $this->admin->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $document->fresh() ?? $document;
    }

    public function test_document_metadata_chips_are_suggested_and_applied_one_by_one(): void {
        $this->enable(DocumentMetadataSuggestionService::CAPABILITY, local: true);
        $document = $this->documentWithText();
        $this->fake->extractionResponse = [
            'document_type' => 'contract',
            'title' => 'Wartungsvertrag Anlage 1',
            'valid_from' => null,
            'valid_until' => '2027-12-31',
        ];

        $this->actingAs($this->admin)
            ->from(route('documents.show', $document))
            ->post(route('ai.assist.document', $document))
            ->assertSessionHas('success');

        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ExtractRequest::class, $sent);
        $this->assertStringContainsString('Wartungsvertrag', $sent->text);
        $this->assertArrayHasKey('document_type', $sent->schema);
        $this->assertStringContainsString('contract', $sent->schema['document_type']);

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $chips = DocumentMetadataSuggestionService::extractedValues($suggestion);
        $this->assertSame(['document_type', 'title', 'valid_until'], array_column($chips, 'field'));

        // Nie Auto-Apply: erst der Klick auf einen Chip ändert das Dokument.
        $this->assertSame(DocumentType::Other, $document->fresh()?->document_type);

        $this->actingAs($this->admin)
            ->from(route('documents.show', $document))
            ->post(route('ai.assist.apply', $suggestion), ['field' => 'valid_until'])
            ->assertSessionHas('success');

        $this->assertSame('2027-12-31', $document->fresh()?->valid_until?->toDateString());
        $this->assertSame(DocumentType::Other, $document->fresh()?->document_type);
        $this->assertCount(2, DocumentMetadataSuggestionService::extractedValues($suggestion->fresh()));
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.suggestion_decided']);
    }

    public function test_unknown_document_types_and_invented_dates_are_dropped(): void {
        $this->enable(DocumentMetadataSuggestionService::CAPABILITY, local: true);
        $document = $this->documentWithText();
        $this->fake->extractionResponse = [
            'document_type' => 'geheimvertrag',
            'title' => null,
            'valid_from' => 'irgendwann 2027',
            'valid_until' => null,
        ];

        $this->actingAs($this->admin)
            ->from(route('documents.show', $document))
            ->post(route('ai.assist.document', $document))
            ->assertSessionHas('success');

        // Katalog-/Formatgarantie: kein Chip → kein Vorschlag.
        $this->assertSame(0, AiTextSuggestion::query()->withoutGlobalScopes()->count());
    }

    public function test_document_without_extractable_text_reports_an_error(): void {
        $this->enable(DocumentMetadataSuggestionService::CAPABILITY, local: true);
        $document = Document::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('documents.show', $document))
            ->post(route('ai.assist.document', $document))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_document_analysis_is_local_only(): void {
        // `high`-Capability mit Cloud-Verbindung → keine Kandidaten.
        $this->enable(DocumentMetadataSuggestionService::CAPABILITY);
        $document = $this->documentWithText();

        $this->actingAs($this->admin)
            ->from(route('documents.show', $document))
            ->post(route('ai.assist.document', $document))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->fake->callCount());
    }

    // ── (h) import.column_mapping ────────────────────────────────────────────

    private function importRun(string $csv): ImportRun {
        $path = 'imports/' . $this->organization->id . '/kunden.csv';
        Storage::disk(CsvPreflightAnalyzer::DISK)->put($path, $csv);

        return ImportRun::create([
            'organization_id' => $this->organization->id,
            'entity' => ImportEntity::Customers,
            'state' => ImportRunState::Failed,
            'input_filename' => 'kunden.csv',
            'input_hash' => str_repeat('a', 64),
            'storage_path' => $path,
            'delimiter' => ';',
            'match_policy' => 'auto_create',
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_only_unknown_header_cells_are_sent_and_mapped_to_free_spec_columns(): void {
        $this->enable(ImportMappingSuggestionService::CAPABILITY);
        // „Name" und „Kundennummer" kennt der HeaderMapper (Alias) — nur die
        // dritte Spalte geht an die KI.
        $run = $this->importRun("Name;Kundennummer;Bezeichnung des Betriebs\nMeier;K-1;Meier GmbH\n");
        $this->fake->classificationResponse = ['company'];

        $this->actingAs($this->admin)
            ->from(route('admin.imports.show', $run))
            ->post(route('ai.assist.import-mapping', $run))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->fake->callCount('classify'));
        $sent = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ClassifyRequest::class, $sent);
        $this->assertSame('Bezeichnung des Betriebs', $sent->text);
        $this->assertFalse($sent->multiple);
        $this->assertContains('company', $sent->catalog);
        $this->assertNotContains('name', $sent->catalog, 'Bereits zugeordnete Spalten stehen nicht mehr im Katalog.');

        $suggestion = AiTextSuggestion::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(
            [['header' => 'Bezeichnung des Betriebs', 'column' => 'company']],
            ImportMappingSuggestionService::mappingValues($suggestion),
        );

        // Reiner Hinweis: der Lauf bleibt unverändert.
        $this->assertSame(ImportRunState::Failed, $run->fresh()?->state);

        $this->actingAs($this->admin)
            ->get(route('admin.imports.show', $run))
            ->assertOk()
            ->assertSee('Bezeichnung des Betriebs');
    }

    public function test_no_suggestion_when_every_header_is_already_known(): void {
        $this->enable(ImportMappingSuggestionService::CAPABILITY);
        $run = $this->importRun("Name;Kundennummer\nMeier;K-1\n");

        $this->actingAs($this->admin)
            ->from(route('admin.imports.show', $run))
            ->post(route('ai.assist.import-mapping', $run))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->fake->callCount());
        $this->assertSame(0, AiTextSuggestion::query()->withoutGlobalScopes()->count());
    }

    public function test_invented_columns_are_discarded(): void {
        $this->enable(ImportMappingSuggestionService::CAPABILITY);
        $run = $this->importRun("Name;Irgendwas Unbekanntes\nMeier;x\n");
        $this->fake->classificationResponse = ['erfundene_spalte'];

        $this->actingAs($this->admin)
            ->from(route('admin.imports.show', $run))
            ->post(route('ai.assist.import-mapping', $run))
            ->assertSessionHas('success');

        $this->assertSame(0, AiTextSuggestion::query()->withoutGlobalScopes()->count());
    }

    public function test_import_mapping_requires_the_entity_permission(): void {
        $this->enable(ImportMappingSuggestionService::CAPABILITY);
        $run = $this->importRun("Name;Irgendwas\nMeier;x\n");
        $user = $this->orgUser();
        $user->givePermissionTo([Permission::AiUse->value]);

        $this->actingAs($user)
            ->post(route('ai.assist.import-mapping', $run))
            ->assertForbidden();
        $this->assertSame(0, $this->fake->callCount());
    }

    public function test_mapping_button_is_hidden_when_the_capability_is_off(): void {
        $run = $this->importRun("Name;Irgendwas\nMeier;x\n");

        $this->actingAs($this->admin)
            ->get(route('admin.imports.show', $run))
            ->assertOk()
            ->assertDontSee(route('ai.assist.import-mapping', $run), false);
    }
}
