<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\User\{Permission, UserRole};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Protokolle prüfen ihre Rechte (lk-06): anlegen mit protocol.create, Fotos
 * mit protocol.item.photo.add/remove, eigene Protokolle sehen mit protocol.view.
 */
class ProtocolPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_creating_protocols_follows_protocol_create(): void {
        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Aussendienst->value))->allows('create', Protocol::class));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Buchhaltung->value))->allows('create', Protocol::class));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('create', Protocol::class));
    }

    public function test_photos_and_viewing_follow_their_rights(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $own = $this->protocolBy($worker);
        $this->assertTrue(Gate::forUser($worker)->allows('addPhoto', $own));
        $this->assertTrue(Gate::forUser($worker)->allows('removePhoto', $own));
        $this->assertTrue(Gate::forUser($worker)->allows('view', $own));

        $viewer = $this->actorIn($this->organization, [Permission::ProtocolView]);
        $viewerProtocol = $this->protocolBy($viewer);
        $this->assertTrue(Gate::forUser($viewer)->allows('update', $viewerProtocol), 'Ersteller pflegt den Entwurf');
        $this->assertFalse(Gate::forUser($viewer)->allows('addPhoto', $viewerProtocol));
        $this->assertFalse(Gate::forUser($viewer)->allows('removePhoto', $viewerProtocol));

        $bare = $this->actorIn($this->organization);
        $this->assertFalse(Gate::forUser($bare)->allows('view', $this->protocolBy($bare)));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('addPhoto', $viewerProtocol));
    }

    private function protocolBy(User $creator): Protocol {
        $entry = DiaryEntry::factory()->for($creator)->create(['organization_id' => $this->organization->id]);
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_id' => $entry->id,
            'created_by_user_id' => $creator->id,
        ]);
        $this->actAsTeam($this->organization);

        return $protocol;
    }
}
