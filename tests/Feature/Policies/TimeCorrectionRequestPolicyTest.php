<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionRequestPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission as P;
use App\Models\{Organization, TimeCorrectionRequest, User};
use App\Policies\TimeCorrectionRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Stempel-Korrekturanträge (MVP-017): submit/withdraw nur durch den
 * ANTRAGSTELLER entlang der Status-Maschine (Draft→Submitted→Approved→
 * Applied); approve/reject/apply sind getrennte Rechte; jede Aktion hart
 * organisationsgebunden.
 */
final class TimeCorrectionRequestPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private TimeCorrectionRequestPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new TimeCorrectionRequestPolicy;
    }

    private function request(User $subject, User $requester, TimeCorrectionStatus $status, ?int $orgId = null): TimeCorrectionRequest {
        $request = new TimeCorrectionRequest;
        $request->organization_id = $orgId ?? $this->organization->id;
        $request->user_id = $subject->id;
        $request->requested_by_user_id = $requester->id;
        $request->status = $status;

        return $request;
    }

    public function test_requester_submits_and_withdraws_along_status_machine(): void {
        $requester = $this->actorIn($this->organization, [P::CorrectionCreateOwn, P::CorrectionSubmitOwn, P::CorrectionWithdrawOwn]);
        $draft = $this->request($requester, $requester, TimeCorrectionStatus::Draft);
        $submitted = $this->request($requester, $requester, TimeCorrectionStatus::Submitted);
        $approved = $this->request($requester, $requester, TimeCorrectionStatus::Approved);

        $this->assertTrue($this->policy->create($requester));
        $this->assertTrue($this->policy->view($requester, $draft));
        $this->assertTrue($this->policy->submit($requester, $draft));
        $this->assertFalse($this->policy->submit($requester, $submitted), 'Nur Drafts sind einreichbar.');
        $this->assertTrue($this->policy->withdraw($requester, $draft));
        $this->assertTrue($this->policy->withdraw($requester, $submitted));
        $this->assertFalse($this->policy->withdraw($requester, $approved), 'Genehmigte Anträge sind nicht zurückziehbar.');
    }

    public function test_decision_rights_follow_status_machine(): void {
        $requester = $this->actorIn($this->organization, [P::CorrectionSubmitOwn]);
        $approver = $this->actorIn($this->organization, [P::CorrectionApprove, P::CorrectionReject]);
        $applier = $this->actorIn($this->organization, [P::CorrectionApplySystem]);
        $draft = $this->request($requester, $requester, TimeCorrectionStatus::Draft);
        $submitted = $this->request($requester, $requester, TimeCorrectionStatus::Submitted);
        $approved = $this->request($requester, $requester, TimeCorrectionStatus::Approved);

        $this->assertTrue($this->policy->approve($approver, $submitted));
        $this->assertTrue($this->policy->reject($approver, $submitted));
        $this->assertFalse($this->policy->approve($approver, $draft), 'Drafts sind nicht entscheidbar.');
        $this->assertFalse($this->policy->approve($requester, $submitted), 'Antragsteller entscheidet nicht (kein Recht).');
        $this->assertTrue($this->policy->apply($applier, $approved));
        $this->assertFalse($this->policy->apply($applier, $submitted), 'Übernehmen erst nach Genehmigung.');
    }

    public function test_view_is_limited_to_involved_or_org_viewers(): void {
        $subject = $this->actorIn($this->organization);
        $requester = $this->actorIn($this->organization, [P::CorrectionCreateOwn]);
        $stranger = $this->actorIn($this->organization, [P::CorrectionCreateOwn]);
        $orgViewer = $this->actorIn($this->organization, [P::CorrectionViewOrganization]);
        $request = $this->request($subject, $requester, TimeCorrectionStatus::Submitted);

        $this->assertTrue($this->policy->view($subject, $request), 'Betroffener sieht seinen Antrag.');
        $this->assertTrue($this->policy->view($requester, $request), 'Antragsteller sieht seinen Antrag.');
        $this->assertFalse($this->policy->view($stranger, $request), 'Unbeteiligte ohne Team-/Org-Recht sehen nichts.');
        $this->assertTrue($this->policy->view($orgViewer, $request));
    }

    public function test_foreign_org_is_denied_even_with_all_permissions(): void {
        $requester = $this->actorIn($this->organization, [P::CorrectionSubmitOwn]);
        $request = $this->request($requester, $requester, TimeCorrectionStatus::Submitted);

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::CorrectionViewOrganization, P::CorrectionApprove, P::CorrectionReject, P::CorrectionApplySystem]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($this->policy->view($attacker, $request));
        $this->assertFalse($this->policy->approve($attacker, $request));
        $this->assertFalse($this->policy->reject($attacker, $request));
        $this->assertFalse($this->policy->apply($attacker, $this->request($requester, $requester, TimeCorrectionStatus::Approved)));
    }
}
