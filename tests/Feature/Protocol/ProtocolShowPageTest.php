<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolShowPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Models\{ExternalParticipant, Organization, Protocol, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rang 28: Protokoll-Detailseite als Trägerseite — Rendering, Rechte,
 * Mandantengrenze und der Externe-Beteiligte-Flow mit type=protocol.
 */
class ProtocolShowPageTest extends TestCase {
    use RefreshDatabase;

    public function test_creator_sees_detail_page_with_participant_panel(): void {
        $user = User::factory()->user()->create();
        // Panel rendert nur mit Manage-Recht oder vorhandenen Beteiligten.
        $user->givePermissionTo('externalParticipant.manage');
        $protocol = Protocol::factory()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'title' => 'Abnahme Heizungsanlage',
        ]);

        $response = $this->actingAs($user)->get(route('protocols.show', $protocol));

        $response->assertOk();
        $response->assertSee('Abnahme Heizungsanlage');
        $response->assertSee(__('external.panel.title'));
    }

    public function test_foreign_org_gets_404_and_stranger_403(): void {
        $owner = User::factory()->user()->create();
        $protocol = Protocol::factory()->create([
            'organization_id' => $owner->organization_id,
            'created_by_user_id' => $owner->id,
        ]);

        // Fremde Organisation: Sqid löst nicht auf → 404.
        $foreignOrg = Organization::factory()->create();
        $foreign = User::factory()->user()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($foreign)->get(route('protocols.show', $protocol))->assertNotFound();

        // Gleiche Org, kein Ersteller, kein ProtocolViewAny → 403.
        $stranger = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $this->actingAs($stranger)->get(route('protocols.show', $protocol))->assertForbidden();
    }

    public function test_external_participant_can_be_invited_for_protocol(): void {
        $user = User::factory()->user()->create();
        $user->givePermissionTo('externalParticipant.manage');
        $protocol = Protocol::factory()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('protocols.show', $protocol))
            ->post(route('external.store', ['type' => 'protocol', 'id' => $protocol->getRouteKey()]), [
                'name' => 'Prüfer Schmidt',
                'party' => 'inspector',
                'abilities' => ['comment'],
                'ttl_days' => 7,
            ]);

        $response->assertRedirect(route('protocols.show', $protocol));
        $response->assertSessionHas('external_participant_link');

        $participant = ExternalParticipant::query()->firstOrFail();
        $this->assertSame($protocol->getMorphClass(), $participant->subject_type);
        $this->assertSame((int) $protocol->id, (int) $participant->subject_id);

        // Panel auf der Detailseite zeigt den Beteiligten.
        $this->actingAs($user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee('Prüfer Schmidt');
    }
}
