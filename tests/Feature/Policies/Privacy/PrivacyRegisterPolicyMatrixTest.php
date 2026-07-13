<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyRegisterPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Privacy;

use App\Models\{Organization, User};
use App\Models\Privacy\{ComplianceFinding, Dpia, Incident, JointControllerAgreement, ProcessingAgreement, Processor, TechnicalMeasure};
use App\Policies\Privacy\{ComplianceFindingPolicy, DpiaPolicy, IncidentPolicy, JointControllerAgreementPolicy, ProcessingAgreementPolicy, ProcessorPolicy, TechnicalMeasurePolicy};
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Matrix-Test über die uniformen Datenschutz-Register-Policies (Feature 043):
 * DSFA, Vorfälle, GVV, AVV, Dienstleister, TOM, Lückenanalyse. Vertrag für
 * alle: dataprotection.*-Rechte (außerhalb des Permission-Enums, daher OHNE
 * Admin-Bypass), Objektzugriff strikt organisationsgebunden (sameOrg).
 */
final class PrivacyRegisterPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        DataProtectionPermissions::ensurePermissionsExist();
        $this->actAsTeam($this->organization);
    }

    /**
     * @return array<string, array{class-string, class-string<Model>, string, string, bool}>
     *                       [policy, model, readPerm, managePerm, hasDelete]
     */
    public static function registerPolicies(): array {
        return [
            'dpia' => [DpiaPolicy::class, Dpia::class, 'dataprotection.view', 'dataprotection.dpia.manage', false],
            'incident' => [IncidentPolicy::class, Incident::class, 'dataprotection.incident.manage', 'dataprotection.incident.manage', false],
            'joint-controller' => [JointControllerAgreementPolicy::class, JointControllerAgreement::class, 'dataprotection.view', 'dataprotection.avv.manage', false],
            'processing-agreement' => [ProcessingAgreementPolicy::class, ProcessingAgreement::class, 'dataprotection.view', 'dataprotection.avv.manage', true],
            'processor' => [ProcessorPolicy::class, Processor::class, 'dataprotection.view', 'dataprotection.avv.manage', true],
            'technical-measure' => [TechnicalMeasurePolicy::class, TechnicalMeasure::class, 'dataprotection.view', 'dataprotection.tom.manage', false],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function subject(string $modelClass, ?int $orgId = null): Model {
        /** @var Model&object{organization_id: int|null} $subject */
        $subject = new $modelClass;
        $subject->organization_id = $orgId ?? $this->organization->id;

        return $subject;
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('registerPolicies')]
    public function test_read_and_manage_matrix(string $policyClass, string $modelClass, string $readPerm, string $managePerm, bool $hasDelete): void {
        $policy = new $policyClass;
        $subject = $this->subject($modelClass);

        $reader = $this->actorIn($this->organization, [$readPerm]);
        $this->assertTrue($policy->viewAny($reader), 'Leserecht muss viewAny erlauben.');
        $this->assertTrue($policy->view($reader, $subject), 'Leserecht muss view (same org) erlauben.');
        if ($readPerm !== $managePerm) {
            $this->assertFalse($policy->create($reader), 'Nur-Leser darf nicht anlegen.');
            $this->assertFalse($policy->update($reader, $subject), 'Nur-Leser darf nicht ändern.');
        }

        $manager = $this->actorIn($this->organization, [$managePerm]);
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $subject));
        if ($hasDelete) {
            $this->assertTrue($policy->delete($manager, $subject));
        }

        $nobody = $this->actorIn($this->organization);
        $this->assertFalse($policy->viewAny($nobody));
        $this->assertFalse($policy->view($nobody, $subject));
        $this->assertFalse($policy->update($nobody, $subject));
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('registerPolicies')]
    public function test_foreign_org_is_always_denied_even_with_all_permissions(string $policyClass, string $modelClass, string $readPerm, string $managePerm, bool $hasDelete): void {
        $policy = new $policyClass;
        $subject = $this->subject($modelClass); // Primär-Org

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, DataProtectionPermissions::ALL);

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($policy->view($attacker, $subject), 'Fremd-Org darf trotz Recht nicht lesen.');
        $this->assertFalse($policy->update($attacker, $subject), 'Fremd-Org darf trotz Recht nicht ändern.');
        if ($hasDelete) {
            $this->assertFalse($policy->delete($attacker, $subject), 'Fremd-Org darf trotz Recht nicht löschen.');
        }
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('registerPolicies')]
    public function test_admins_have_no_bypass(string $policyClass, string $modelClass, string $readPerm, string $managePerm, bool $hasDelete): void {
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $subject = $this->subject($modelClass);

        foreach ([$orgAdmin, $platformAdmin] as $admin) {
            $this->assertTrue(Gate::forUser($admin)->denies('viewAny', $modelClass));
            $this->assertTrue(Gate::forUser($admin)->denies('view', $subject));
            $this->assertTrue(Gate::forUser($admin)->denies('update', $subject));
        }
    }

    public function test_compliance_finding_policy_contract(): void {
        $policy = new ComplianceFindingPolicy;
        $finding = new ComplianceFinding;
        $finding->organization_id = $this->organization->id;

        $viewer = $this->actorIn($this->organization, ['dataprotection.view']);
        $decider = $this->actorIn($this->organization, ['dataprotection.compliance.manage']);

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertFalse($policy->manage($viewer));
        $this->assertFalse($policy->update($viewer, $finding));

        $this->assertTrue($policy->manage($decider));
        $this->assertTrue($policy->update($decider, $finding));

        // Fremd-Org trotz Entscheidungsrecht abgewiesen.
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, ['dataprotection.compliance.manage']);
        $this->actAsTeam($foreignOrg);
        $this->assertFalse($policy->update($attacker, $finding));

        // Kein Admin-Bypass.
        $admin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $this->assertTrue(Gate::forUser($admin)->denies('update', $finding));
    }
}
