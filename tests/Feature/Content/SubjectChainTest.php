<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Content;

use App\Enums\Document\DocumentType;
use App\Enums\Knowledge\ArticleStatus;
use App\Models\Customer\Customer;
use App\Models\DiaryEntry;
use App\Models\Document\Document;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Content\ContentSubjectResolver;
use App\Services\Document\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Bezugsachse (MVP-818, Feature 155): Kunde, Projekt und Träger eines
 * Inhalts sind in jeder übergreifenden Liste sichtbar und filterbar. Vorher
 * zeigte die Wissenszentrale gar keinen Bezug und die Dokumentenliste nur die
 * direkte Kante — zu welchem Kunden ein Dokument am Projekt gehörte, stand
 * nirgends.
 */
final class SubjectChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Müller Haustechnik',
        ]);
    }

    public function test_document_list_shows_the_customer_behind_a_project(): void {
        $project = $this->project('Dachsanierung');
        $this->document('Abnahmeprotokoll Dach', $project);

        $this->actingAs($this->admin)->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Abnahmeprotokoll Dach')
            // Beide Glieder der Kette, nicht nur der Träger.
            ->assertSee('Müller Haustechnik')
            ->assertSee('Dachsanierung');
    }

    public function test_customer_filter_finds_documents_across_the_whole_chain(): void {
        $project = $this->project('Dachsanierung');
        $entry = $this->diaryEntry('Heizungstausch');
        $this->document('Angebot Dach', $project);
        $this->document('Abnahme Heizung', $entry);
        $this->document('Fremdes Papier', null);

        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schmidt GmbH']);
        $this->document('Papier Schmidt', $other);

        $this->actingAs($this->admin)
            ->get(route('documents.index', ['customer' => $this->customer->sqid]))
            ->assertOk()
            ->assertSee('Angebot Dach')
            ->assertSee('Abnahme Heizung')
            ->assertDontSee('Fremdes Papier')
            ->assertDontSee('Papier Schmidt');
    }

    public function test_customer_file_shows_documents_of_projects_and_orders(): void {
        $project = $this->project('Dachsanierung');
        $entry = $this->diaryEntry('Heizungstausch');
        $this->document('Direkt am Kunden', $this->customer);
        $this->document('Angebot Dach', $project);
        $this->document('Abnahme Heizung', $entry);

        // Bis MVP-818 zeigte die Akte nur das direkt zugeordnete Dokument,
        // während das Kundenportal die ganze Kette kannte.
        $this->actingAs($this->admin)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('Direkt am Kunden')
            ->assertSee('Angebot Dach')
            ->assertSee('Abnahme Heizung');
    }

    public function test_order_page_carries_its_own_document_panel(): void {
        $entry = $this->diaryEntry('Heizungstausch');
        $this->document('Abnahme Heizung', $entry);

        $this->actingAs($this->admin)->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('Abnahme Heizung');
    }

    public function test_knowledge_hub_shows_and_filters_by_customer(): void {
        $project = $this->project('Dachsanierung');
        $this->document('Angebot Dach', $project);
        KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Heizung entlüften',
            'status' => ArticleStatus::Published->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('knowledge-hub.index'))
            ->assertOk()
            ->assertSee('Angebot Dach')
            ->assertSee('Heizung entlüften')
            ->assertSee('Müller Haustechnik');

        // Ein Wissensartikel gehört keinem Kunden — der Kundenfilter blendet ihn aus,
        // statt ihn mangels Bezug durchzulassen.
        $this->actingAs($this->admin)
            ->get(route('knowledge-hub.index', ['customer' => $this->customer->sqid]))
            ->assertOk()
            ->assertSee('Angebot Dach')
            ->assertDontSee('Heizung entlüften');
    }

    public function test_document_page_renders_the_chain_without_breaking_markup(): void {
        $asset = \App\Models\Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'ZR-DB-SRV01A',
        ]);
        $document = $this->document('Bedienungsanleitung', $asset);

        $html = (string) $this->actingAs($this->admin)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->getContent();

        // Die Kette stand zunächst im :subtitle, den die Toolbar zusätzlich als
        // title-Attribut setzt: das Markup beendete das Attribut am ersten
        // Anführungszeichen, `">` wurde sichtbar und der Kopf erschien doppelt.
        $this->assertSame(
            0,
            preg_match('/title="[^"]*</', $html),
            'Kein Markup in einem title-Attribut — sonst bricht das Attribut auf.',
        );
        // Sichtbarer Text zwischen den Tags; das zusätzliche aria-label der
        // Kette (Typ + Name) ist gewollt und zählt hier nicht mit.
        $this->assertSame(
            1,
            substr_count($html, '>ZR-DB-SRV01A<'),
            'Der Bezug steht genau einmal sichtbar im Kopf, nicht doppelt.',
        );
        $this->assertStringContainsString('Müller Haustechnik', $html);
    }

    public function test_resolver_walks_from_document_to_customer(): void {
        $entry = $this->diaryEntry('Heizungstausch');
        $entry->project()->associate($this->project('Dachsanierung'))->save();
        $document = $this->document('Abnahme Heizung', $entry);

        $subject = app(ContentSubjectResolver::class)->resolve($document->fresh(['documentable']));

        $this->assertSame((int) $this->customer->id, (int) $subject->customer?->id);
        $this->assertSame('Dachsanierung', $subject->project?->name);
        $this->assertSame('Heizungstausch', $subject->carrier?->getAttribute('title'));
        $this->assertCount(3, $subject->chain());
    }

    public function test_an_article_has_no_subject(): void {
        $article = KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Heizung entlüften',
            'status' => ArticleStatus::Published->value,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->assertTrue(app(ContentSubjectResolver::class)->resolve($article)->isEmpty());
    }

    private function project(string $name): Project {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
        ]);
    }

    private function diaryEntry(string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'title' => $title,
        ]);
    }

    private function document(string $title, ?object $carrier): Document {
        return app(DocumentService::class)->create(
            $carrier,
            $this->admin,
            ['title' => $title, 'document_type' => DocumentType::Other->value],
            UploadedFile::fake()->create('datei.pdf', 20, 'application/pdf'),
        );
    }
}
