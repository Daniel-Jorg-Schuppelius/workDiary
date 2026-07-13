<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Organization, User};
use App\Policies\OrganizationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Mandantenverwaltung — die zentrale Cross-Tenant-Grenze: Organization trägt
 * KEINEN OrganizationScope, das Route-Binding löst jeden Mandanten global auf.
 * Nur der PLATTFORM-Betreiber (isGlobalAdmin) erhält den before()-Voll-Bypass;
 * ein org-lokaler Admin darf ausschließlich die EIGENE Organisation sehen/
 * bearbeiten und niemals fremde Mandanten auflisten, exportieren, deaktivieren
 * oder löschen. manage-members ist vom Bypass ausgenommen (Plattform-Admin
 * ohne Org-Kontext hat keinen Mitgliederzugriff).
 */
final class OrganizationPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private OrganizationPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new OrganizationPolicy;
    }

    public function test_platform_admin_has_full_bypass_across_tenants(): void {
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $foreignOrg = Organization::factory()->create();

        $gate = Gate::forUser($platformAdmin);
        $this->assertTrue($gate->allows('viewAny', Organization::class));
        $this->assertTrue($gate->allows('view', $foreignOrg));
        $this->assertTrue($gate->allows('create', Organization::class));
        $this->assertTrue($gate->allows('update', $foreignOrg));
        $this->assertTrue($gate->allows('delete', $foreignOrg));
        $this->assertTrue($gate->allows('purge', $foreignOrg));
        $this->assertTrue($gate->allows('export', $foreignOrg));
        $this->assertTrue($gate->allows('deactivate', $foreignOrg));
        $this->assertTrue($gate->allows('reactivate', $foreignOrg));
    }

    public function test_org_admin_manages_only_own_org(): void {
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        $this->assertTrue($this->policy->view($orgAdmin, $this->organization));
        $this->assertTrue($this->policy->update($orgAdmin, $this->organization));
        $this->assertTrue($this->policy->manageBranding($orgAdmin, $this->organization));
        $this->assertTrue($this->policy->manageMembers($orgAdmin));
    }

    public function test_org_admin_never_reaches_foreign_tenants(): void {
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $foreignOrg = Organization::factory()->create();

        $gate = Gate::forUser($orgAdmin);
        $this->assertTrue($gate->denies('viewAny', Organization::class), 'Mandantenliste nur für Plattform-Betreiber.');
        $this->assertTrue($gate->denies('view', $foreignOrg));
        $this->assertTrue($gate->denies('update', $foreignOrg));
        $this->assertTrue($gate->denies('create', Organization::class));
        $this->assertTrue($gate->denies('delete', $this->organization), 'Nicht mal die eigene Org darf ein Org-Admin löschen.');
        $this->assertTrue($gate->denies('purge', $foreignOrg));
        $this->assertTrue($gate->denies('export', $foreignOrg));
        $this->assertTrue($gate->denies('deactivate', $foreignOrg));
        $this->assertTrue($gate->denies('manageBranding', $foreignOrg), 'Branding fremder Orgs ist tabu.');
    }

    public function test_manage_members_is_excluded_from_platform_bypass(): void {
        // before() liefert für manage-members explizit null — die Methode entscheidet.
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $this->assertNull($this->policy->before($platformAdmin, 'manage-members'));

        // Plattform-Admin OHNE Org-Kontext hat keinen Mitgliederzugriff.
        $orglessPlatformAdmin = User::factory()->create(['organization_id' => null, 'is_platform_admin' => true]);
        $this->assertFalse($this->policy->manageMembers($orglessPlatformAdmin));
    }

    public function test_regular_user_has_no_org_administration(): void {
        $user = $this->actorIn($this->organization);

        $this->assertFalse($this->policy->view($user, $this->organization));
        $this->assertFalse($this->policy->update($user, $this->organization));
        $this->assertFalse($this->policy->manageMembers($user));
        $this->assertFalse($this->policy->manageBranding($user, $this->organization));
    }
}
