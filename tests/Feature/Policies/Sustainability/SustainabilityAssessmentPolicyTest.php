<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessmentPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Sustainability;

use App\Enums\User\Permission as P;
use App\Models\Sustainability\SustainabilityAssessment;
use App\Policies\Sustainability\SustainabilityAssessmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * ESG-Bewertungen (Feature 071): sustainability.view/viewAny/manage; FINALE
 * Bewertungen sind auf Policy-Ebene für Manage-Nutzer unveränderlich
 * (update ⇒ false). Design-Hinweis: die Unveränderlichkeit gilt AUCH für
 * Admins — der Policy-Admin-Bypass wird dafür bewusst im Controller mit
 * einem harten 403 übersteuert (SustainabilityAssessmentService/Controller),
 * finale Stände werden nur versioniert, nie mutiert.
 */
final class SustainabilityAssessmentPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private SustainabilityAssessmentPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new SustainabilityAssessmentPolicy;
    }

    private function assessment(string $status): SustainabilityAssessment {
        $assessment = new SustainabilityAssessment;
        $assessment->status = $status;

        return $assessment;
    }

    public function test_viewer_reads_but_never_writes(): void {
        $viewer = $this->actorIn($this->organization, [P::SustainabilityViewAny, P::SustainabilityView]);
        $draft = $this->assessment('draft');

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $draft));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $draft));
        $this->assertFalse($this->policy->manage($viewer, $draft));
    }

    public function test_manager_edits_drafts_but_final_is_immutable(): void {
        $manager = $this->actorIn($this->organization, [P::SustainabilityManage]);
        $draft = $this->assessment('draft');
        $final = $this->assessment('final');

        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $draft));
        // Kernvertrag Feature 071: finale Bewertung ist unveränderlich —
        // es wird versioniert, nie mutiert.
        $this->assertFalse($this->policy->update($manager, $final));
        $this->assertTrue($this->policy->manage($manager, $final), 'Finalisieren/Versionieren bleibt möglich.');
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $nobody = $this->actorIn($this->organization);
        $draft = $this->assessment('draft');

        $this->assertFalse($this->policy->viewAny($nobody));
        $this->assertFalse($this->policy->view($nobody, $draft));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
