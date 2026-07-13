<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceDeskPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Change, Organization, Problem, RequestItem, ServiceQueue};
use App\Policies\{ChangePolicy, ProblemPolicy, RequestItemPolicy, ServiceQueuePolicy};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Matrix-Test über die uniformen ServiceDesk-Policies (Feature 065):
 * Changes, Probleme, Katalog-Items und Queues — Lesen mit serviceTicket.view,
 * Pflege mit dem jeweiligen Manage-Recht, Objektzugriff strikt
 * organisationsgebunden (sameOrg hart, BEWUSST ohne Admin-Bypass).
 */
final class ServiceDeskPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    /**
     * @return array<string, array{class-string, class-string<Model>, P, bool}>
     *                       [policy, model, managePerm, hasDelete]
     */
    public static function serviceDeskPolicies(): array {
        return [
            'change' => [ChangePolicy::class, Change::class, P::ServiceDeskChangeManage, false],
            'problem' => [ProblemPolicy::class, Problem::class, P::ServiceDeskProblemManage, false],
            'request-item' => [RequestItemPolicy::class, RequestItem::class, P::ServiceCatalogManage, true],
            'service-queue' => [ServiceQueuePolicy::class, ServiceQueue::class, P::HelpdeskQueueManage, true],
        ];
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('serviceDeskPolicies')]
    public function test_read_and_manage_matrix(string $policyClass, string $modelClass, P $managePerm, bool $hasDelete): void {
        $policy = new $policyClass;
        /** @var Model&object{organization_id: int|null} $subject */
        $subject = new $modelClass;
        $subject->organization_id = $this->organization->id;

        $viewer = $this->actorIn($this->organization, [P::ServiceTicketView]);
        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $subject));
        $this->assertFalse($policy->create($viewer));
        $this->assertFalse($policy->update($viewer, $subject));

        $manager = $this->actorIn($this->organization, [$managePerm]);
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $subject));
        if ($hasDelete) {
            $this->assertTrue($policy->delete($manager, $subject));
        }

        $nobody = $this->actorIn($this->organization);
        $this->assertFalse($policy->viewAny($nobody));
        $this->assertFalse($policy->viewAny($this->orglessActor()));
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('serviceDeskPolicies')]
    public function test_foreign_org_is_denied_even_with_permissions(string $policyClass, string $modelClass, P $managePerm, bool $hasDelete): void {
        $policy = new $policyClass;
        /** @var Model&object{organization_id: int|null} $subject */
        $subject = new $modelClass;
        $subject->organization_id = $this->organization->id;

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::ServiceTicketView, $managePerm]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($policy->view($attacker, $subject));
        $this->assertFalse($policy->update($attacker, $subject));
        if ($hasDelete) {
            $this->assertFalse($policy->delete($attacker, $subject));
        }
    }
}
