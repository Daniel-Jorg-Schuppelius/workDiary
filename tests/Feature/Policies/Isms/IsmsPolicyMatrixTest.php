<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Isms\{IsmsAdvisory, IsmsAudit, IsmsAuditPackage, IsmsControl, IsmsManagementReview, IsmsNormStatus, IsmsRequirement, IsmsRisk, IsmsScope, IsmsSecurityIncident, IsmsSoftwareInstallation, IsmsSoftwareProduct, IsmsSupplierAssessment, IsmsVulnerability};
use App\Models\User;
use App\Policies\Isms\{IsmsAdvisoryPolicy, IsmsAuditPackagePolicy, IsmsAuditPolicy, IsmsControlPolicy, IsmsManagementReviewPolicy, IsmsNormStatusPolicy, IsmsRequirementPolicy, IsmsRiskPolicy, IsmsScopePolicy, IsmsSecurityIncidentPolicy, IsmsSoftwareInstallationPolicy, IsmsSoftwareProductPolicy, IsmsSupplierAssessmentPolicy, IsmsVulnerabilityPolicy};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Matrix-Test über die 14 uniformen ISMS-Policies (Feature 044/046):
 * Lesen mit isms.viewAny/isms.view, Pflege ausschließlich mit isms.manage,
 * Admin-Bypass via HasAdminBypass. Die Mandantengrenze tragen NICHT die
 * Policies, sondern der OrganizationScope der Modelle (BelongsToOrganization)
 * — dieser Vertrag wird hier mit abgesichert, damit eine später entfernte
 * Trait-Einbindung nicht unbemerkt eine Cross-Tenant-Fläche öffnet.
 */
final class IsmsPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    /**
     * @return array<string, array{class-string, class-string<Model>, list<string>, list<string>}>
     *                       [policy, model, manage-Methoden mit Objekt, manage-Methoden ohne Objekt]
     */
    public static function uniformPolicies(): array {
        return [
            'advisory' => [IsmsAdvisoryPolicy::class, IsmsAdvisory::class, [], ['create']],
            'audit' => [IsmsAuditPolicy::class, IsmsAudit::class, ['update', 'delete', 'transition', 'manageFindings'], ['create']],
            'audit-package' => [IsmsAuditPackagePolicy::class, IsmsAuditPackage::class, ['update', 'delete', 'finalize', 'manageTokens'], ['create']],
            'control' => [IsmsControlPolicy::class, IsmsControl::class, ['update', 'delete'], ['create', 'import']],
            'management-review' => [IsmsManagementReviewPolicy::class, IsmsManagementReview::class, ['update', 'delete', 'approve'], ['create']],
            'norm-status' => [IsmsNormStatusPolicy::class, IsmsNormStatus::class, ['update', 'delete', 'transition', 'addCertificate'], ['create']],
            'requirement' => [IsmsRequirementPolicy::class, IsmsRequirement::class, ['update', 'delete'], ['create', 'import', 'updateStatement']],
            'risk' => [IsmsRiskPolicy::class, IsmsRisk::class, ['update', 'delete', 'transition'], ['create']],
            'security-incident' => [IsmsSecurityIncidentPolicy::class, IsmsSecurityIncident::class, ['update', 'delete', 'transition'], ['create']],
            'software-installation' => [IsmsSoftwareInstallationPolicy::class, IsmsSoftwareInstallation::class, ['update', 'delete'], ['create']],
            'software-product' => [IsmsSoftwareProductPolicy::class, IsmsSoftwareProduct::class, ['update', 'delete'], ['create']],
            'supplier-assessment' => [IsmsSupplierAssessmentPolicy::class, IsmsSupplierAssessment::class, ['update', 'delete', 'transition'], ['create']],
            'vulnerability' => [IsmsVulnerabilityPolicy::class, IsmsVulnerability::class, ['update', 'delete', 'transition'], ['create']],
        ];
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $objectMethods
     * @param  list<string>  $plainMethods
     */
    #[DataProvider('uniformPolicies')]
    public function test_reader_can_view_but_not_manage(string $policyClass, string $modelClass, array $objectMethods, array $plainMethods): void {
        $policy = new $policyClass;
        $subject = new $modelClass;
        $reader = $this->actorIn($this->organization, [P::IsmsViewAny, P::IsmsView]);

        $this->assertTrue($policy->viewAny($reader));
        $this->assertTrue($policy->view($reader, $subject));
        foreach ($objectMethods as $method) {
            $this->assertFalse($policy->{$method}($reader, $subject), "Nur-Leser darf {$method} nicht.");
        }
        foreach ($plainMethods as $method) {
            $this->assertFalse($policy->{$method}($reader), "Nur-Leser darf {$method} nicht.");
        }
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $objectMethods
     * @param  list<string>  $plainMethods
     */
    #[DataProvider('uniformPolicies')]
    public function test_manager_can_manage_and_nobody_without_permission(string $policyClass, string $modelClass, array $objectMethods, array $plainMethods): void {
        $policy = new $policyClass;
        $subject = new $modelClass;

        $manager = $this->actorIn($this->organization, [P::IsmsManage]);
        foreach ($objectMethods as $method) {
            $this->assertTrue($policy->{$method}($manager, $subject), "isms.manage muss {$method} erlauben.");
        }
        foreach ($plainMethods as $method) {
            $this->assertTrue($policy->{$method}($manager), "isms.manage muss {$method} erlauben.");
        }

        $nobody = $this->actorIn($this->organization);
        $this->assertFalse($policy->viewAny($nobody));
        $this->assertFalse($policy->view($nobody, $subject));

        $orgless = $this->orglessActor();
        $this->assertFalse($policy->viewAny($orgless));
    }

    /**
     * Die Mandantengrenze trägt der OrganizationScope: jede ISMS-Ressource
     * MUSS BelongsToOrganization nutzen, weil die Policies bewusst keinen
     * sameOrg-Check führen.
     *
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $objectMethods
     * @param  list<string>  $plainMethods
     */
    #[DataProvider('uniformPolicies')]
    public function test_tenant_boundary_is_carried_by_organization_scope(string $policyClass, string $modelClass, array $objectMethods, array $plainMethods): void {
        $this->assertContains(
            BelongsToOrganization::class,
            class_uses_recursive($modelClass),
            "{$modelClass} muss BelongsToOrganization nutzen — die Policy prüft bewusst keine Org-Grenze.",
        );
    }

    public function test_scope_requires_manage_even_for_reading(): void {
        $policy = new IsmsScopePolicy;
        $scope = new IsmsScope;
        $scope->is_default = false;

        $reader = $this->actorIn($this->organization, [P::IsmsViewAny, P::IsmsView]);
        $this->assertFalse($policy->viewAny($reader), 'Scope-Definition ist reine Manage-Fläche.');
        $this->assertFalse($policy->view($reader, $scope));

        $manager = $this->actorIn($this->organization, [P::IsmsManage]);
        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->update($manager, $scope));
        $this->assertTrue($policy->delete($manager, $scope));

        $defaultScope = new IsmsScope;
        $defaultScope->is_default = true;
        $this->assertFalse($policy->delete($manager, $defaultScope), 'Default-Scope ist unlöschbar.');

        $this->assertContains(BelongsToOrganization::class, class_uses_recursive(IsmsScope::class));
    }

    public function test_audit_package_download_and_verify_follow_read_rights(): void {
        $policy = new IsmsAuditPackagePolicy;
        $package = new IsmsAuditPackage;

        $reader = $this->actorIn($this->organization, [P::IsmsViewAny, P::IsmsView]);
        $this->assertTrue($policy->verify($reader, $package), 'Integritätsprüfung ist Leserecht.');
        $this->assertTrue($policy->download($reader, $package), 'Interner Download ist Leserecht.');
        $this->assertFalse($policy->manageTokens($reader, $package), 'Prüfer-Tokens nur mit isms.manage.');
    }

    public function test_admin_bypass_applies(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        $audit = new IsmsAudit;
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', IsmsAudit::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $audit));
        $this->assertTrue(Gate::forUser($admin)->allows('manageFindings', $audit));
    }
}
