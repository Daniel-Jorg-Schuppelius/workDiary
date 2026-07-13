<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosurePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\{DayClosure, Organization, User};
use App\Policies\DayClosurePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Tagesabschlüsse (ArbZG-Nachweis): save/close/requestCorrection nur für den
 * EIGENEN Tag; Korrektur-Entscheidung (approveCorrection) und Admin-Reopen
 * sind separate Rechte mit Status-Maschine (Open→Closed→Correction);
 * jede Aktion hart organisationsgebunden.
 */
final class DayClosurePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private DayClosurePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new DayClosurePolicy;
    }

    private function closure(User $owner, DayClosureStatus $status, ?int $orgId = null): DayClosure {
        $closure = new DayClosure;
        $closure->organization_id = $orgId ?? $this->organization->id;
        $closure->user_id = $owner->id;
        $closure->status = $status;

        return $closure;
    }

    public function test_owner_saves_and_closes_own_open_day(): void {
        $owner = $this->actorIn($this->organization, [P::DayCloseViewOwn, P::DayCloseCloseOwn]);
        $open = $this->closure($owner, DayClosureStatus::Open);
        $closed = $this->closure($owner, DayClosureStatus::Closed);

        $this->assertTrue($this->policy->view($owner, $open));
        $this->assertTrue($this->policy->save($owner, $open));
        $this->assertTrue($this->policy->close($owner, $open));
        $this->assertFalse($this->policy->close($owner, $closed), 'Nur offene Tage sind abschließbar.');
    }

    public function test_correction_flow_follows_status_machine(): void {
        $owner = $this->actorIn($this->organization, [P::DayCloseViewOwn, P::DayCloseRequestCorrectionOwn]);
        $approver = $this->actorIn($this->organization, [P::DayCloseApproveCorrection]);
        $closed = $this->closure($owner, DayClosureStatus::Closed);
        $correction = $this->closure($owner, DayClosureStatus::Correction);
        $open = $this->closure($owner, DayClosureStatus::Open);

        $this->assertTrue($this->policy->requestCorrection($owner, $closed));
        $this->assertFalse($this->policy->requestCorrection($owner, $open), 'Korrektur nur für geschlossene Tage.');
        $this->assertTrue($this->policy->approveCorrection($approver, $correction));
        $this->assertFalse($this->policy->approveCorrection($approver, $closed), 'Entscheidung nur im Correction-Status.');
        $this->assertFalse($this->policy->approveCorrection($owner, $correction), 'Antragsteller entscheidet nicht selbst (kein Recht).');
    }

    public function test_foreign_users_and_orgs_are_denied(): void {
        $owner = $this->actorIn($this->organization, [P::DayCloseViewOwn, P::DayCloseCloseOwn]);
        $colleague = $this->actorIn($this->organization, [P::DayCloseViewOwn, P::DayCloseCloseOwn]);
        $open = $this->closure($owner, DayClosureStatus::Open);

        // Kollege: kein view/save/close fremder Tage ohne Team-/Org-Leserecht.
        $this->assertFalse($this->policy->view($colleague, $open));
        $this->assertFalse($this->policy->save($colleague, $open));
        $this->assertFalse($this->policy->close($colleague, $open));

        // Org-Leserecht öffnet NUR view.
        $orgViewer = $this->actorIn($this->organization, [P::DayCloseViewOrganization]);
        $this->assertTrue($this->policy->view($orgViewer, $open));
        $this->assertFalse($this->policy->save($orgViewer, $open));

        // Fremd-Org trotz aller Rechte hart ausgeschlossen.
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::DayCloseViewOwn, P::DayCloseViewOrganization, P::DayCloseApproveCorrection, P::DayCloseReopen]);
        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $open));
        $this->assertFalse($this->policy->approveCorrection($attacker, $this->closure($owner, DayClosureStatus::Correction)));
        $this->assertFalse($this->policy->reopen($attacker, $this->closure($owner, DayClosureStatus::Closed)));
    }

    public function test_reopen_requires_permission_and_closed_status(): void {
        $owner = $this->actorIn($this->organization, [P::DayCloseViewOwn]);
        $reopener = $this->actorIn($this->organization, [P::DayCloseReopen]);
        $closed = $this->closure($owner, DayClosureStatus::Closed);
        $open = $this->closure($owner, DayClosureStatus::Open);

        $this->assertTrue($this->policy->reopen($reopener, $closed));
        $this->assertFalse($this->policy->reopen($reopener, $open));
        $this->assertFalse($this->policy->reopen($owner, $closed), 'Reopen ohne Antrag ist Admin-/Sonderrecht.');
    }
}
