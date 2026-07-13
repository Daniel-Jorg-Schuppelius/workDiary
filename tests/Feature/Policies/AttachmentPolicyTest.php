<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Attachment, Organization, User};
use App\Policies\AttachmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Anhänge: Defense-in-Depth zusätzlich zum OrganizationScope — die Policy
 * verweigert Fremd-Org-Anhänge anhand der AKTIVEN Organisation
 * (currentOrganization-Binding, Fallback User-Org), damit die Grenze auch in
 * Konsolen-/Queue-Kontexten ohne Global Scope hält. Org-lose (globale/legacy)
 * Anhänge bleiben lesbar; Löschen verlangt Eigentum UND Org-Grenze.
 */
final class AttachmentPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private AttachmentPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new AttachmentPolicy;
    }

    private function attachment(?int $orgId, ?int $ownerId = null): Attachment {
        $attachment = new Attachment;
        $attachment->organization_id = $orgId;
        $attachment->user_id = $ownerId;

        return $attachment;
    }

    public function test_same_org_attachment_is_viewable(): void {
        $user = $this->actorIn($this->organization);

        $this->assertTrue($this->policy->view($user, $this->attachment($this->organization->id)));
        $this->assertTrue($this->policy->create($user));
    }

    public function test_foreign_org_attachment_is_denied_even_for_owner(): void {
        $foreignOrg = Organization::factory()->create();
        $user = $this->actorIn($this->organization);
        // currentOrganization = Primär-Org (setUp) — Anhang gehört zur Fremd-Org.
        $foreign = $this->attachment($foreignOrg->id, $user->id);

        $this->assertFalse($this->policy->view($user, $foreign));
        $this->assertFalse($this->policy->delete($user, $foreign), 'Selbst der Eigentümer kommt über die Org-Grenze nicht hinweg.');
    }

    public function test_orgless_global_attachment_stays_readable(): void {
        $user = $this->actorIn($this->organization);

        $this->assertTrue($this->policy->view($user, $this->attachment(null)), 'Globale/Legacy-Anhänge (z. B. Plattform-Logo) bleiben lesbar.');
    }

    public function test_delete_requires_ownership(): void {
        $owner = $this->actorIn($this->organization);
        $other = $this->actorIn($this->organization);
        $attachment = $this->attachment($this->organization->id, $owner->id);

        $this->assertTrue($this->policy->delete($owner, $attachment));
        $this->assertFalse($this->policy->delete($other, $attachment));
    }

    public function test_without_current_organization_user_org_is_the_boundary(): void {
        // Queue-/Konsolen-Kontext: kein currentOrganization-Binding.
        app()->forgetInstance('currentOrganization');
        $user = $this->actorIn($this->organization);
        $foreignOrg = Organization::factory()->create();

        $this->assertTrue($this->policy->view($user, $this->attachment($this->organization->id)));
        $this->assertFalse($this->policy->view($user, $this->attachment($foreignOrg->id)));

        $orgless = $this->orglessActor();
        $this->assertFalse($this->policy->view($orgless, $this->attachment($this->organization->id)), 'Org-loser User ohne aktiven Kontext sieht keine org-gebundenen Anhänge.');
    }
}
