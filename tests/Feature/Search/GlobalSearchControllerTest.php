<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Search;

use App\Models\{CommunicationNote, Customer, Document, FormSubmission, FormTemplate, KnowledgeArticle, Organization, Project, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class GlobalSearchControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_short_query_returns_empty_groups(): void {
        $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'a']))
            ->assertOk()
            ->assertJson(['groups' => []]);
    }

    public function test_finds_customers_and_projects_in_org(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Industries GmbH',
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Acme Webportal',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'acme']))
            ->assertOk();

        $data = $response->json();
        $keys = collect($data['groups'])->pluck('key')->all();
        $this->assertContains('customers', $keys);
        $this->assertContains('projects', $keys);

        $customerGroup = collect($data['groups'])->firstWhere('key', 'customers');
        $this->assertSame('Acme Industries GmbH', $customerGroup['items'][0]['title']);

        $projectGroup = collect($data['groups'])->firstWhere('key', 'projects');
        $this->assertSame('Acme Webportal', $projectGroup['items'][0]['title']);
        $this->assertSame('Acme Industries GmbH', $projectGroup['items'][0]['subtitle']);
    }

    public function test_does_not_leak_across_organizations(): void {
        $otherOrg = \App\Models\Organization::factory()->create();
        Customer::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign Kunde XYZ',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'foreign']))
            ->assertOk();

        $this->assertSame([], $response->json('groups'));
    }

    public function test_requires_authentication(): void {
        $this->getJson(route('api.internal.search', ['q' => 'acme']))
            ->assertUnauthorized();
    }

    // ── Kommunikationsnotizen (MVP-012) ─────────────────────────────────────

    public function test_finds_communication_notes_by_subject(): void {
        $note = CommunicationNote::factory()->create([
            'subject' => 'Zuluwort Rückruf Angebot',
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'communication');
        $this->assertNotNull($group, 'Kommunikations-Gruppe fehlt.');
        $this->assertSame('Zuluwort Rückruf Angebot', $group['items'][0]['title']);
        $this->assertStringContainsString('#communication-note-' . $note->id, $group['items'][0]['url']);
    }

    public function test_confidential_note_is_hidden_from_third_parties(): void {
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        CommunicationNote::factory()->confidential()->create([
            'subject' => 'Zuluwort Gehaltsthema vertraulich',
            'created_by_user_id' => $creator->id,
        ]);

        // Dritter (ohne communication.confidential.manage) sieht die Notiz NICHT …
        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();
        $this->assertNull(
            collect($response->json('groups'))->firstWhere('key', 'communication'),
            'Vertrauliche Notiz darf für Dritte nicht in der Suche erscheinen.'
        );

        // … der Erfasser dagegen schon.
        $response = $this->actingAs($creator)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();
        $this->assertNotNull(
            collect($response->json('groups'))->firstWhere('key', 'communication'),
            'Der Erfasser muss seine vertrauliche Notiz finden.'
        );
    }

    // ── Dokumente (MVP-031) ─────────────────────────────────────────────────

    public function test_finds_documents_by_title(): void {
        Document::factory()->create([
            'title' => 'Zuluwort Wartungsvertrag 2026',
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'documents');
        $this->assertNotNull($group, 'Dokumente-Gruppe fehlt.');
        $this->assertSame('Zuluwort Wartungsvertrag 2026', $group['items'][0]['title']);
        $this->assertStringContainsString(route('documents.index'), $group['items'][0]['url']);
    }

    public function test_documents_hidden_without_view_any_permission(): void {
        Document::factory()->create([
            'title' => 'Zuluwort Wartungsvertrag 2026',
            'created_by_user_id' => $this->user->id,
        ]);
        // Außendienst trägt KEIN document.viewAny (PermissionsSeeder).
        $aussendienst = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($aussendienst)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $this->assertNull(
            collect($response->json('groups'))->firstWhere('key', 'documents'),
            'Ohne document.viewAny darf kein Dokument-Treffer erscheinen.'
        );
    }

    // ── Wissensbasis (Feature 011) ──────────────────────────────────────────

    public function test_finds_published_knowledge_articles_and_own_drafts(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Zuluwort Druckerstau beheben',
            'created_by_user_id' => $colleague->id,
        ]);
        KnowledgeArticle::factory()->create([
            'title' => 'Zuluwort eigener Entwurf',
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'knowledge');
        $this->assertNotNull($group, 'Wissensbasis-Gruppe fehlt.');
        $titles = collect($group['items'])->pluck('title')->all();
        $this->assertContains('Zuluwort Druckerstau beheben', $titles);
        $this->assertContains('Zuluwort eigener Entwurf', $titles);
    }

    public function test_foreign_draft_article_is_hidden(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        KnowledgeArticle::factory()->create([
            'title' => 'Zuluwort fremder Entwurf',
            'created_by_user_id' => $colleague->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $this->assertNull(
            collect($response->json('groups'))->firstWhere('key', 'knowledge'),
            'Fremde Entwürfe dürfen ohne knowledge.publish nicht erscheinen.'
        );
    }

    // ── Formulare (Feature 032) ─────────────────────────────────────────────

    public function test_finds_form_submissions_by_template_name(): void {
        $template = FormTemplate::factory()->create([
            'name' => 'Zuluwort Prüfprotokoll',
            'created_by_user_id' => $this->user->id,
        ]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'forms');
        $this->assertNotNull($group, 'Formulare-Gruppe fehlt.');
        $this->assertSame('Zuluwort Prüfprotokoll', $group['items'][0]['title']);
        $this->assertSame(route('form-submissions.show', $submission), $group['items'][0]['url']);
    }

    public function test_foreign_submission_hidden_without_template_view_any(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $template = FormTemplate::factory()->create([
            'name' => 'Zuluwort Prüfprotokoll',
            'created_by_user_id' => $colleague->id,
        ]);
        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $colleague->id,
        ]);

        // Einfacher User (ohne formTemplate.viewAny) sieht fremde Submissions NICHT …
        $response = $this->actingAs($this->user)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();
        $this->assertNull(
            collect($response->json('groups'))->firstWhere('key', 'forms'),
            'Fremde Submissions dürfen ohne formTemplate.viewAny nicht erscheinen.'
        );

        // … Teamleitung (formTemplate.viewAny) dagegen schon.
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $response = $this->actingAs($teamlead)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();
        $this->assertNotNull(
            collect($response->json('groups'))->firstWhere('key', 'forms'),
            'Teamleitung muss alle Submissions der Organisation finden.'
        );
    }

    // ── Modul-Gating (Plan/Lizenz) ──────────────────────────────────────────

    public function test_free_plan_hides_documents_knowledge_and_forms_groups(): void {
        $freeOrg = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $freeOrg);
        $admin = User::factory()->admin()->create(['organization_id' => $freeOrg->id]);

        Document::factory()->create([
            'title' => 'Zuluwort Dokument',
            'created_by_user_id' => $admin->id,
        ]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Zuluwort Artikel',
            'created_by_user_id' => $admin->id,
        ]);
        $template = FormTemplate::factory()->create([
            'name' => 'Zuluwort Formular',
            'created_by_user_id' => $admin->id,
        ]);
        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by_user_id' => $admin->id,
        ]);
        // Kommunikationsnotizen sind NICHT modul-gegatet und bleiben sichtbar.
        CommunicationNote::factory()->create([
            'subject' => 'Zuluwort Notiz',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('api.internal.search', ['q' => 'zuluwort']))
            ->assertOk();

        $keys = collect($response->json('groups'))->pluck('key')->all();
        $this->assertNotContains('documents', $keys, 'Free-Plan darf keine Dokument-Treffer liefern.');
        $this->assertNotContains('knowledge', $keys, 'Free-Plan darf keine Wissensbasis-Treffer liefern.');
        $this->assertNotContains('forms', $keys, 'Free-Plan darf keine Formular-Treffer liefern.');
        $this->assertContains('communication', $keys, 'Kommunikationsnotizen sind nicht modul-gegatet.');
    }
}
