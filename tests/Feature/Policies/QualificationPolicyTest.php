<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Organization, Qualification, User};
use App\Policies\QualificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Qualifikationen: Katalog liest jeder, Objekt-Sicht und Pflege sind
 * organisationsgebunden; Pflege nur durch Admins — und auch ein Admin
 * NUR in der eigenen Organisation (sameOrg hart in update/delete).
 */
final class QualificationPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private QualificationPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new QualificationPolicy;
    }

    private function qualification(?int $orgId = null): Qualification {
        $qualification = new Qualification;
        $qualification->organization_id = $orgId ?? $this->organization->id;

        return $qualification;
    }

    public function test_admin_manages_own_org_only(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $own = $this->qualification();
        $foreign = $this->qualification(Organization::factory()->create()->id);
        // Org-Anlage (Observer seedet Rollen) verstellt den Team-Kontext — zurücksetzen.
        $this->actAsTeam($this->organization);

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $own));
        $this->assertTrue($this->policy->delete($admin, $own));
        // sameOrg hart: fremde Org auch für Admins tabu (Methoden-Ebene).
        $this->assertFalse($this->policy->update($admin, $foreign));
        $this->assertFalse($this->policy->delete($admin, $foreign));
    }

    public function test_regular_user_reads_own_org_but_never_writes(): void {
        $user = $this->actorIn($this->organization);
        $own = $this->qualification();
        $foreign = $this->qualification(Organization::factory()->create()->id);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $own));
        $this->assertFalse($this->policy->view($user, $foreign));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $own));
        $this->assertFalse($this->policy->delete($user, $own));
    }
}
