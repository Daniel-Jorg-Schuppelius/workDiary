<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Organization, ShiftExchange, User};
use App\Policies\ShiftExchangePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Schichttausch: Sichtbar nur für Beteiligte (Anbieter/Ziel) oder Genehmiger;
 * accept nur durch den festgelegten Ziel-Kollegen bzw. bei offener Abgabe
 * (target NULL) durch jeden Tauschberechtigten; cancel nur durch den
 * Anbieter; decide nur mit Genehmigungsrecht — alles organisationsgebunden.
 */
final class ShiftExchangePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private ShiftExchangePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new ShiftExchangePolicy;
    }

    private function exchange(User $requester, ?User $target = null, ?int $orgId = null): ShiftExchange {
        $exchange = new ShiftExchange;
        $exchange->organization_id = $orgId ?? $this->organization->id;
        $exchange->requested_by_user_id = $requester->id;
        $exchange->target_user_id = $target?->id;

        return $exchange;
    }

    public function test_participants_and_approver_may_view(): void {
        $requester = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $target = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $approver = $this->actorIn($this->organization, [P::ShiftExchangeApprove]);
        $stranger = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $exchange = $this->exchange($requester, $target);

        $this->assertTrue($this->policy->create($requester));
        $this->assertTrue($this->policy->view($requester, $exchange));
        $this->assertTrue($this->policy->view($target, $exchange));
        $this->assertTrue($this->policy->view($approver, $exchange));
        $this->assertFalse($this->policy->view($stranger, $exchange), 'Unbeteiligte sehen fremde Tauschanfragen nicht.');
    }

    public function test_accept_respects_target_binding(): void {
        $requester = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $target = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $colleague = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);

        $directed = $this->exchange($requester, $target);
        $this->assertTrue($this->policy->accept($target, $directed));
        $this->assertFalse($this->policy->accept($colleague, $directed), 'Gerichtete Abgabe nimmt nur der Ziel-Kollege an.');

        $open = $this->exchange($requester);
        $this->assertTrue($this->policy->accept($colleague, $open), 'Offene Abgabe darf jeder Tauschberechtigte annehmen.');
    }

    public function test_cancel_only_by_requester_and_decide_only_by_approver(): void {
        $requester = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $target = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $approver = $this->actorIn($this->organization, [P::ShiftExchangeApprove]);
        $exchange = $this->exchange($requester, $target);

        $this->assertTrue($this->policy->cancel($requester, $exchange));
        $this->assertFalse($this->policy->cancel($target, $exchange));
        $this->assertTrue($this->policy->decide($approver, $exchange));
        $this->assertFalse($this->policy->decide($requester, $exchange));
    }

    public function test_foreign_org_is_denied_even_with_permissions(): void {
        $requester = $this->actorIn($this->organization, [P::ShiftExchangeRequest]);
        $exchange = $this->exchange($requester);

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::ShiftExchangeRequest, P::ShiftExchangeApprove]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($this->policy->view($attacker, $exchange));
        $this->assertFalse($this->policy->accept($attacker, $exchange), 'Offene Abgabe endet an der Org-Grenze.');
        $this->assertFalse($this->policy->decide($attacker, $exchange));
    }
}
