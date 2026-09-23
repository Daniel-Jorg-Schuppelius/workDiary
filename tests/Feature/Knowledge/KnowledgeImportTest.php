<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Communication\CommunicationNote;
use App\Models\Integration\ExternalReference;
use App\Models\Knowledge\{ContentCollection, ContentReference, KnowledgeArticle};
use App\Models\Platform\User;
use App\Models\Plugins\Msgraph\MsgraphOneNoteConnection;
use App\Plugins\Msgraph\Api\MsgraphOneNoteClient;
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Intake\{IntakeChangePage, IntakeItem};
use App\Services\Collections\Import\{KnowledgeImportService, ObsidianVaultReader, OneNoteNotebookReader};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\Support\{FakeIntakeAdapter, FakePluginHttp};
use Tests\TestCase;

/**
 * Einbahn-Übernahme aus Obsidian und OneNote (MVP-815, Feature 155).
 */
final class KnowledgeImportTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_markdown_front_matter_tags_and_wikilinks_are_read(): void {
        $document = app(ObsidianVaultReader::class)->parse('id-1', 'Projekte/Heizung Schule.md', <<<'MD'
            ---
            title: Heizung Grundschule
            tags: [wartung, "kunde/stadt"]
            aliases:
              - Schulheizung
            ---
            # Überblick
            Siehe [[Kunden/Stadtwerke|Stadtwerke]] und [[Pumpe#Einstellung]], Bild ![[plan.png]].
            Stand #offen, nicht #123 und nicht im Code `#intern`.
            ```
            #auch-nicht
            ```
            MD);

        $this->assertSame('Heizung Grundschule', $document->title);
        $this->assertSame(['Projekte'], $document->folders);
        $this->assertSame(['wartung', 'kunde/stadt', 'offen'], $document->tags);
        $this->assertSame(['Kunden/Stadtwerke', 'Pumpe'], $document->linkNames);
        $this->assertContains('Schulheizung', $document->names);
        $this->assertContains('Projekte/Heizung Schule', $document->names);
        $this->assertStringStartsWith('# Überblick', $document->text);
    }

    public function test_an_obsidian_vault_becomes_notes_in_collections_with_references_and_origin(): void {
        [$connection, $adapter] = $this->vault([
            ['v1', 'Tresor/Kunden/Stadtwerke.md', "Ansprechpartner: Frau Kurz\n#kunde"],
            ['v2', 'Tresor/Projekte/Heizung.md', "---\ntags: wartung\n---\nFür [[Stadtwerke]] erledigt, siehe [[Fehlt]]."],
            ['v3', 'Tresor/.obsidian/workspace.md', 'intern'],
            ['v4', 'Anderes/Privat.md', 'nicht im Tresor'],
        ]);

        $report = $this->runObsidian($connection, $adapter, 'Tresor');

        $this->assertSame(2, $report->created);
        $this->assertSame(1, $report->linksResolved);
        $this->assertSame(1, $report->linksUnresolved);
        $this->assertSame(['Heizung', 'Stadtwerke'], CommunicationNote::query()->orderBy('subject')->pluck('subject')->all());

        $root = ContentCollection::query()->whereNull('parent_id')->where('title', 'Tresor')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Kunden', 'Projekte'], ContentCollection::query()->where('parent_id', $root->id)->pluck('title')->all());

        $heizung = CommunicationNote::query()->where('subject', 'Heizung')->firstOrFail();
        $stadtwerke = CommunicationNote::query()->where('subject', 'Stadtwerke')->firstOrFail();
        $this->assertSame(['wartung'], $heizung->tags->pluck('name')->all());
        $this->assertSame(['kunde'], $stadtwerke->tags->pluck('name')->all());
        $this->assertTrue(ContentReference::query()
            ->where('source_id', $heizung->id)->where('target_id', $stadtwerke->id)
            ->where('kind', ContentReference::KIND_MENTIONED)->exists());

        $origin = ExternalReference::query()->withoutGlobalScopes()->forReferenceable($heizung)->firstOrFail();
        $this->assertSame('obsidian_note', $origin->external_type);
        $this->assertStringContainsString('Projekte/Heizung.md', (string) $origin->payload['source']);

        $this->actingAs($this->admin)->get(route('communication-notes.show', $heizung))
            ->assertOk()
            ->assertSee('Projekte/Heizung.md');
    }

    public function test_a_second_run_neither_duplicates_nor_downloads_again(): void {
        [$connection, $adapter] = $this->vault([
            ['v1', 'Tresor/Eins.md', 'Erste Notiz verweist auf [[Zwei]].'],
        ]);
        $this->runObsidian($connection, $adapter, 'Tresor');

        // Zweiter Lauf: die alte Datei hat keinen Inhalt mehr im Fake — ein Download würde scheitern.
        [, $adapter] = $this->vault([
            ['v1', 'Tresor/Eins.md', null],
            ['v2', 'Tresor/Zwei.md', 'Zweite Notiz.'],
        ], $connection);
        $report = $this->runObsidian($connection, $adapter, 'Tresor');

        $this->assertSame(1, $report->created);
        $this->assertSame(1, $report->skipped);
        $this->assertSame(2, CommunicationNote::query()->count());
        $this->assertSame(1, ContentCollection::query()->where('title', 'Tresor')->count());
    }

    public function test_the_reader_stops_at_the_limit_and_reports_it(): void {
        [$connection, $adapter] = $this->vault([
            ['v1', 'Tresor/A.md', 'a'],
            ['v2', 'Tresor/B.md', 'b'],
        ]);

        $read = app(ObsidianVaultReader::class)->documents($connection, $adapter, 'Tresor', 1, static fn (): bool => false);

        $this->assertCount(1, $read['documents']);
        $this->assertTrue($read['limited']);
    }

    public function test_notes_can_become_article_drafts_instead(): void {
        [$connection, $adapter] = $this->vault([
            ['v1', 'Tresor/Pumpe entlüften.md', 'Ventil öffnen.'],
        ]);

        $this->runObsidian($connection, $adapter, 'Tresor', KnowledgeImportService::TARGET_ARTICLE);

        $article = KnowledgeArticle::query()->where('title', 'Pumpe entlüften')->firstOrFail();
        $this->assertSame('Ventil öffnen.', $article->problem);
        $this->assertSame(0, CommunicationNote::query()->count());
    }

    public function test_onenote_pages_become_notes_per_section(): void {
        config(['plugins.msgraph.client_id' => 'cid', 'plugins.msgraph.client_secret' => 'sec', 'plugins.msgraph.onenote_import' => true]);
        $connection = MsgraphOneNoteConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'status' => MsgraphOneNoteConnection::STATUS_ACTIVE,
        ]);
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/onenote/notebooks/nb-1/sections*' => FakePluginHttp::response(['value' => [['id' => 'sec-1', 'displayName' => 'Baustellen']]]),
            'https://graph.microsoft.com/v1.0/me/onenote/notebooks/nb-1/sectionGroups*' => FakePluginHttp::response(['value' => [
                ['id' => 'grp-1', 'displayName' => 'Archiv', 'sections' => [['id' => 'sec-2', 'displayName' => '2025']]],
            ]]),
            'https://graph.microsoft.com/v1.0/me/onenote/sections/sec-1/pages*' => FakePluginHttp::response(['value' => [
                ['id' => 'page-1', 'title' => 'Rohbau Halle', 'lastModifiedDateTime' => '2026-09-01T08:00:00Z'],
            ]]),
            'https://graph.microsoft.com/v1.0/me/onenote/sections/sec-2/pages*' => FakePluginHttp::response(['value' => [
                ['id' => 'page-2', 'title' => 'Abnahme 2025', 'lastModifiedDateTime' => '2025-12-01T08:00:00Z'],
            ]]),
            'https://graph.microsoft.com/v1.0/me/onenote/pages/page-1/content' => FakePluginHttp::response('<html><head><title>x</title></head><body><p>Beton &amp; Stahl</p><ul><li>Decke</li><li>Wände</li></ul><script>alert(1)</script></body></html>'),
            'https://graph.microsoft.com/v1.0/me/onenote/pages/page-2/content' => FakePluginHttp::response('<p>Ohne Mängel</p>'),
        ]);

        $imports = app(KnowledgeImportService::class);
        $read = app(OneNoteNotebookReader::class)->documents(new MsgraphOneNoteClient($connection), 'nb-1', 'Bau', 10,
            $imports->knownChecker($this->organization, 'msgraph', 'onenote_page'));
        $report = $imports->import($this->organization, $this->admin, 'msgraph', 'onenote_page', 'Bau', KnowledgeImportService::TARGET_NOTE, $read['documents']);

        $this->assertSame(2, $report->created);
        $page = CommunicationNote::query()->where('subject', 'Rohbau Halle')->firstOrFail();
        $this->assertSame("Beton & Stahl\n\n- Decke\n- Wände", $page->body);
        $bau = ContentCollection::query()->whereNull('parent_id')->where('title', 'Bau')->firstOrFail();
        $archiv = ContentCollection::query()->where('parent_id', $bau->id)->where('title', 'Archiv')->firstOrFail();
        $this->assertTrue(ContentCollection::query()->where('parent_id', $archiv->id)->where('title', '2025')->exists());
    }

    public function test_onenote_needs_the_switch_before_connecting_or_importing(): void {
        config(['plugins.msgraph.client_id' => 'cid', 'plugins.msgraph.client_secret' => 'sec']);
        $this->assertFalse(MsgraphConfig::oneNoteImportEnabled($this->organization->id));
        $this->assertStringNotContainsString('Notes.Read', MsgraphConfig::adminConsentUrl('https://example.test/cb', 'state', $this->organization->id));

        $this->actingAs($this->admin)->post(route('admin.msgraph.onenote.oauth.start'))->assertSessionHas('error');

        config(['plugins.msgraph.onenote_import' => true]);
        $this->assertStringContainsString('Notes.Read', MsgraphConfig::adminConsentUrl('https://example.test/cb', 'state', $this->organization->id));
    }

    public function test_only_admins_open_the_import(): void {
        $this->actingAs($this->admin)->get(route('knowledge-imports.create', ['source' => 'obsidian']))
            ->assertOk()
            ->assertSee(__('collections.import.obsidian.none'));

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($member, [\App\Enums\User\Permission::CollectionManage, \App\Enums\User\Permission::CollectionViewAny, \App\Enums\User\Permission::CommunicationCreate]);
        $this->actingAs($member)->get(route('knowledge-imports.create', ['source' => 'obsidian']))->assertForbidden();
        $this->actingAs($member)->post(route('knowledge-imports.obsidian'), ['connection' => 'x', 'target' => 'note'])->assertForbidden();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string|null}>  $files
     * @return array{0: CloudDocumentConnection, 1: FakeIntakeAdapter}
     */
    private function vault(array $files, ?CloudDocumentConnection $connection = null): array {
        $connection ??= CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Nextcloud,
            'name' => 'Nextcloud Büro',
        ]);
        $adapter = new FakeIntakeAdapter();
        $items = [];
        foreach ($files as [$id, $path, $content]) {
            $items[] = new IntakeItem($id, $path, basename($path), 'rev-1', strlen((string) $content), 'text/markdown', '2026-09-10T08:00:00Z');
            if ($content !== null) {
                $adapter->contents[$id] = $content;
            }
        }
        $adapter->pages = [new IntakeChangePage($items, [], 'done', false)];

        return [$connection, $adapter];
    }

    private function runObsidian(CloudDocumentConnection $connection, FakeIntakeAdapter $adapter, string $vault, string $target = KnowledgeImportService::TARGET_NOTE): \App\Services\Collections\Import\KnowledgeImportReport {
        $imports = app(KnowledgeImportService::class);
        $read = app(ObsidianVaultReader::class)->documents($connection, $adapter, $vault, KnowledgeImportService::MAX_DOCUMENTS,
            $imports->knownChecker($this->organization, 'nextcloud', 'obsidian_note'));

        return $imports->import($this->organization, $this->admin, 'nextcloud', 'obsidian_note', $vault, $target, $read['documents'], $read['limited']);
    }
}
