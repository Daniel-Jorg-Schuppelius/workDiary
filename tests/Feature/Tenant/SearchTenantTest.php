<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{CommunicationNote, Customer, Document, FormSubmission, FormTemplate, KnowledgeArticle, Organization, Project, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die globale Suche (Command-Palette / Spotlight) darf nur Treffer der
 * eigenen Organisation liefern. Diese Tests legen Datensätze mit eindeutigen
 * Suchbegriffen in Org B an und prüfen, dass ein Suchaufruf eines Org-A-Users
 * keinen Treffer enthält.
 *
 * Referenz: ../WorkDiary-Architecture/security/tenant-audit-2026.md (Abschnitt „Globale Suche").
 */
class SearchTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'search-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'search-b']);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->adminB = User::factory()->admin()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_global_search_does_not_return_cross_org_customers(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create([
            'name' => 'ZZSEARCHCUSTBORG',
        ]));

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHCUSTBORG']));
        $response->assertOk();

        // Hinweis: Die API echoed den Suchbegriff als "q" im Body zurück, deshalb
        // prüfen wir strukturiert auf leere groups bzw. fehlende Customer-ID
        // statt String-Containment auf den Raw-Body.
        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf keine Customer-Treffer aus Org B liefern.');
    }

    public function test_global_search_does_not_return_cross_org_projects(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create());
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->for($customerB)->create([
            'name' => 'ZZSEARCHPROJBORG',
        ]));

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHPROJBORG']));
        $response->assertOk();

        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf kein Org-B-Projekt liefern.');
    }

    public function test_global_search_does_not_return_cross_org_communication_notes(): void {
        $this->withOrg($this->orgB, fn() => CommunicationNote::factory()->create([
            'subject' => 'ZZSEARCHNOTEBORG Rückruf',
            'created_by_user_id' => $this->adminB->id,
        ]));

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHNOTEBORG']));
        $response->assertOk();

        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf keine Kommunikationsnotiz aus Org B liefern.');
    }

    public function test_global_search_does_not_return_cross_org_documents(): void {
        $this->withOrg($this->orgB, fn() => Document::factory()->create([
            'title' => 'ZZSEARCHDOCBORG Wartungsvertrag',
            'created_by_user_id' => $this->adminB->id,
        ]));

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHDOCBORG']));
        $response->assertOk();

        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf kein Org-B-Dokument liefern.');
    }

    public function test_global_search_does_not_return_cross_org_knowledge_articles(): void {
        $this->withOrg($this->orgB, fn() => KnowledgeArticle::factory()->published()->create([
            'title' => 'ZZSEARCHKBBORG Druckerstau beheben',
            'created_by_user_id' => $this->adminB->id,
        ]));

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHKBBORG']));
        $response->assertOk();

        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf keinen Org-B-Wissensartikel liefern.');
    }

    public function test_global_search_does_not_return_cross_org_form_submissions(): void {
        $this->withOrg($this->orgB, function () {
            $template = FormTemplate::factory()->create(['name' => 'ZZSEARCHFORMBORG Prüfprotokoll']);

            return FormSubmission::factory()->create([
                'form_template_id' => $template->id,
                'submitted_by_user_id' => $this->adminB->id,
            ]);
        });

        $this->actingAs($this->adminA);
        $response = $this->getJson(route('api.internal.search', ['q' => 'ZZSEARCHFORMBORG']));
        $response->assertOk();

        $groups = (array) $response->json('groups');
        $allItems = collect($groups)->flatMap(fn($g) => $g['items'] ?? [])->all();
        $this->assertEmpty($allItems, 'Cross-Org-Suche darf keine Org-B-Submission liefern.');
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
