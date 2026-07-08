<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentShowPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Document;

use App\Models\{Document, DocumentVersion, ExternalParticipant, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rang 28: Dokument-Detailseite als Trägerseite — Rendering, Rechte,
 * Mandantengrenze, Versionsliste und der Externe-Beteiligte-Flow mit
 * type=document.
 */
class DocumentShowPageTest extends TestCase {
    use RefreshDatabase;

    private function viewer(): User {
        $user = User::factory()->user()->create();
        $user->givePermissionTo(['document.viewAny', 'document.view']);

        return $user;
    }

    public function test_detail_page_renders_with_versions_and_panel(): void {
        $user = $this->viewer();
        // Panel rendert nur mit Manage-Recht oder vorhandenen Beteiligten.
        $user->givePermissionTo('externalParticipant.manage');
        $document = Document::factory()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'title' => 'Wartungsvertrag 2026',
        ]);
        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_no' => 1,
            'uploaded_by_user_id' => $user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        $response = $this->actingAs($user)->get(route('documents.show', $document));

        $response->assertOk();
        $response->assertSee('Wartungsvertrag 2026');
        $response->assertSee('v1');
        $response->assertSee(__('external.panel.title'));
    }

    public function test_guest_redirect_and_org_boundary(): void {
        $owner = $this->viewer();
        $document = Document::factory()->create([
            'organization_id' => $owner->organization_id,
            'created_by_user_id' => $owner->id,
        ]);

        // Gast wird zum Login umgeleitet.
        $this->get(route('documents.show', $document))->assertRedirect();

        // Fremde Organisation: Sqid löst nicht auf → 404.
        $foreignOrg = Organization::factory()->create();
        $foreign = User::factory()->user()->create(['organization_id' => $foreignOrg->id]);
        $foreign->givePermissionTo(['document.viewAny', 'document.view']);
        $this->actingAs($foreign)->get(route('documents.show', $document))->assertNotFound();
    }

    public function test_external_participant_can_be_invited_for_document(): void {
        $user = $this->viewer();
        $user->givePermissionTo(['externalParticipant.manage', 'document.update']);
        $document = Document::factory()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('documents.show', $document))
            ->post(route('external.store', ['type' => 'document', 'id' => $document->getRouteKey()]), [
                'name' => 'Architekt Weber',
                'party' => 'expert',
                'abilities' => ['comment'],
                'ttl_days' => 7,
            ]);

        $response->assertRedirect(route('documents.show', $document));

        $participant = ExternalParticipant::query()->firstOrFail();
        $this->assertSame($document->getMorphClass(), $participant->subject_type);

        $this->actingAs($user)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Architekt Weber');
    }
}
