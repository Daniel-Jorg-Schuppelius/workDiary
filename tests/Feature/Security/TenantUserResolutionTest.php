<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantUserResolutionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Regression zum Whitebox-Befund 2026-07 (MVP-099): das Model User trägt keinen
 * globalen OrganizationScope, daher muss jede Auflösung eines Nutzers per
 * `?user=`/`?user_id=` explizit auf die eigene Organisation gescopt sein — sonst
 * kann ein Admin/Buchhalter der Organisation A über die (aufzählbare) rohe ID
 * einen Nutzer der Organisation B auflösen (Cross-Tenant-Zugriff/-Enumeration).
 */
final class TenantUserResolutionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $foreign;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $otherOrg = Organization::factory()->create();
        $this->foreign = User::factory()->create(['organization_id' => $otherOrg->id]);
    }

    public function test_flex_page_rejects_foreign_org_user_target(): void {
        // Rohe numerische Fremd-ID als Enumerations-/Cross-Tenant-Vektor.
        $this->actingAs($this->admin)
            ->get(route('flex.index', ['user' => (string) $this->foreign->id]))
            ->assertNotFound();
    }

    public function test_work_balance_report_rejects_foreign_org_user_target(): void {
        $this->actingAs($this->admin)
            ->get(route('reports.work-balance', ['user' => (string) $this->foreign->id]))
            ->assertForbidden();
    }

    public function test_own_flex_page_still_renders(): void {
        // Gegenprobe: der eigene Zugriff bleibt unverändert erreichbar.
        $this->actingAs($this->admin)
            ->get(route('flex.index'))
            ->assertOk();
    }
}
