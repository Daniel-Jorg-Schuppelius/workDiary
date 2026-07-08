<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalContactTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ExternalParticipant;

use App\Models\{DiaryEntry, ExternalContact, ExternalParticipant, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Wiederverwendbares externes Kontakt-/Rollenprofil (Feature 033, Rang 30):
 * org-gescopte Stammdatenpflege sowie Vorbelegung + Verknüpfung beim Einladen.
 */
class ExternalContactTest extends TestCase {
    use RefreshDatabase;

    private function manager(): User {
        $user = User::factory()->user()->create();
        $user->givePermissionTo('externalParticipant.manage');

        return $user;
    }

    public function test_create_contact(): void {
        Mail::fake();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('external-contacts.store'), [
                'name' => 'ACME Prüf GmbH',
                'email' => 'p@acme.test',
                'role' => 'Prüfer',
                'party' => 'inspector',
            ])->assertRedirect(route('external-contacts.index'));

        $this->assertDatabaseHas('external_contacts', [
            'name' => 'ACME Prüf GmbH',
            'organization_id' => $manager->organization_id,
            'party' => 'inspector',
        ]);
    }

    public function test_update_and_delete_contact(): void {
        $manager = $this->manager();
        $contact = ExternalContact::factory()->create(['organization_id' => $manager->organization_id, 'name' => 'Alt']);

        $this->actingAs($manager)
            ->put(route('external-contacts.update', $contact), ['name' => 'Neu', 'party' => 'expert'])
            ->assertRedirect(route('external-contacts.index'));
        $this->assertSame('Neu', $contact->fresh()->name);

        $this->actingAs($manager)
            ->delete(route('external-contacts.destroy', $contact))
            ->assertRedirect(route('external-contacts.index'));
        $this->assertDatabaseMissing('external_contacts', ['id' => $contact->id]);
    }

    public function test_index_is_org_scoped(): void {
        $manager = $this->manager();
        ExternalContact::factory()->create(['organization_id' => $manager->organization_id, 'name' => 'EigenesProfil']);
        $otherOrg = Organization::factory()->create();
        ExternalContact::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'FremdesProfil']);

        $this->actingAs($manager)->get(route('external-contacts.index'))
            ->assertOk()
            ->assertSee('EigenesProfil')
            ->assertDontSee('FremdesProfil');
    }

    public function test_invite_with_contact_prefills_and_links(): void {
        Mail::fake();
        $manager = $this->manager();
        $entry = DiaryEntry::factory()->for($manager)->create();
        $contact = ExternalContact::factory()->create([
            'organization_id' => $manager->organization_id,
            'name' => 'Wiederkehrend AG',
            'email' => 'wk@example.test',
            'role' => 'Prüfer',
            'party' => 'inspector',
        ]);

        // Name/Art leer → aus dem Profil gefüllt.
        $this->actingAs($manager)
            ->post(route('external.store', ['type' => 'diary', 'id' => $entry->getRouteKey()]), [
                'external_contact' => $contact->sqid,
                'ttl_days' => 7,
            ])->assertRedirect();

        $participant = ExternalParticipant::query()->firstOrFail();
        $this->assertSame($contact->id, $participant->external_contact_id);
        $this->assertSame('Wiederkehrend AG', $participant->name);
        $this->assertSame('wk@example.test', $participant->email);
        // Der Link wurde auch gemailt (E-Mail aus dem Profil).
        Mail::assertQueued(\App\Mail\ExternalParticipantInvitedMail::class);
    }

    public function test_save_contact_flag_persists_reusable_profile(): void {
        Mail::fake();
        $manager = $this->manager();
        $entry = DiaryEntry::factory()->for($manager)->create();

        $this->actingAs($manager)
            ->post(route('external.store', ['type' => 'diary', 'id' => $entry->getRouteKey()]), [
                'name' => 'Neu und Merken',
                'email' => 'nm@example.test',
                'party' => 'expert',
                'ttl_days' => 7,
                'save_contact' => '1',
            ])->assertRedirect();

        $this->assertDatabaseHas('external_contacts', [
            'name' => 'Neu und Merken',
            'organization_id' => $manager->organization_id,
        ]);
        $this->assertNotNull(ExternalParticipant::query()->firstOrFail()->external_contact_id);
    }

    public function test_manage_requires_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('external-contacts.index'))->assertForbidden();
    }
}
