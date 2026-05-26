<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosurePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\{MonthClosure, User};
use App\Policies\MonthClosurePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class MonthClosurePolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private MonthClosurePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->policy = new MonthClosurePolicy;
    }

    public function test_owner_with_submit_permission_can_submit_own_draft(): void {
        $user = $this->makeUser([P::MonthViewOwn->value, P::MonthSubmitOwn->value]);
        $closure = $this->makeClosure($user, MonthClosureStatus::Draft);

        $this->assertTrue($this->policy->view($user, $closure));
        $this->assertTrue($this->policy->submit($user, $closure));
        $this->assertFalse($this->policy->approve($user, $closure));
    }

    public function test_other_user_cannot_view_or_submit_foreign_closure(): void {
        $owner = $this->makeUser([P::MonthSubmitOwn->value]);
        $other = $this->makeUser([P::MonthViewOwn->value, P::MonthSubmitOwn->value]);
        $closure = $this->makeClosure($owner, MonthClosureStatus::Draft);

        $this->assertFalse($this->policy->view($other, $closure));
        $this->assertFalse($this->policy->submit($other, $closure));
    }

    public function test_approver_can_approve_submitted_but_not_draft(): void {
        $owner = $this->makeUser([]);
        $approver = $this->makeUser([P::MonthApprove->value, P::MonthReject->value]);

        $draft = $this->makeClosure($owner, MonthClosureStatus::Draft);
        $submitted = $this->makeClosure($owner, MonthClosureStatus::Submitted, 2024, 2);

        $this->assertFalse($this->policy->approve($approver, $draft));
        $this->assertTrue($this->policy->approve($approver, $submitted));
        $this->assertTrue($this->policy->reject($approver, $submitted));
    }

    public function test_self_reopen_allowed_for_owner_on_rejected_without_admin_perm(): void {
        $owner = $this->makeUser([P::MonthSubmitOwn->value]);
        $closure = $this->makeClosure($owner, MonthClosureStatus::Rejected);

        $this->assertTrue($this->policy->reopen($owner, $closure));
    }

    public function test_lock_requires_lock_permission_and_approved_status(): void {
        $owner = $this->makeUser([]);
        $admin = $this->makeUser([P::MonthLock->value]);
        $approved = $this->makeClosure($owner, MonthClosureStatus::Approved);
        $draft = $this->makeClosure($owner, MonthClosureStatus::Draft, 2024, 2);

        $this->assertTrue($this->policy->lock($admin, $approved));
        $this->assertFalse($this->policy->lock($admin, $draft));
        $this->assertFalse($this->policy->lock($owner, $approved));
    }

    /**
     * @param  list<string>  $perms
     */
    private function makeUser(array $perms): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        if ($perms !== []) {
            $user->givePermissionTo($perms);
            $user->unsetRelation('permissions');
        }

        return $user;
    }

    private function makeClosure(User $owner, MonthClosureStatus $status, int $year = 2024, int $month = 1): MonthClosure {
        /** @var MonthClosure $closure */
        $closure = MonthClosure::query()->create([
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->id,
            'period_year' => $year,
            'period_month' => $month,
            'status' => $status,
            'days_total' => 31,
            'days_with_attendance' => 0,
            'days_closed' => 0,
            'days_open' => 0,
            'warnings_count' => 0,
        ]);

        return $closure;
    }
}
