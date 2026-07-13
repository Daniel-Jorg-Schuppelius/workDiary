<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTemplatePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{InvoiceTemplate, Organization, User};
use App\Policies\InvoiceTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Rechnungsvorlagen: invoice.*-Rechte (oder Org-Admin), Objektzugriff strikt
 * organisationsgebunden — auch ein Admin einer FREMDEN Organisation darf
 * fremde Vorlagen weder sehen noch ändern (sameOrg hart in der Policy,
 * kein HasAdminBypass).
 */
final class InvoiceTemplatePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private InvoiceTemplatePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new InvoiceTemplatePolicy;
    }

    private function template(?int $orgId = null): InvoiceTemplate {
        $template = new InvoiceTemplate;
        $template->organization_id = $orgId ?? $this->organization->id;

        return $template;
    }

    public function test_invoice_permissions_gate_each_action(): void {
        $template = $this->template();

        $viewer = $this->actorIn($this->organization, [P::InvoiceViewAny]);
        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $template));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $template));
        $this->assertFalse($this->policy->delete($viewer, $template));

        $editor = $this->actorIn($this->organization, [P::InvoiceCreate, P::InvoiceUpdate, P::InvoiceDelete]);
        $this->assertTrue($this->policy->create($editor));
        $this->assertTrue($this->policy->update($editor, $template));
        $this->assertTrue($this->policy->delete($editor, $template));
    }

    public function test_org_admin_may_manage_own_org_templates(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $template = $this->template();

        $this->assertTrue($this->policy->view($admin, $template));
        $this->assertTrue($this->policy->update($admin, $template));
    }

    public function test_foreign_org_is_denied_even_for_admins_and_permission_holders(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $foreignEditor = $this->actorIn($foreignOrg, [P::InvoiceViewAny, P::InvoiceUpdate, P::InvoiceDelete]);
        $template = $this->template(); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($foreignAdmin, $template), 'Fremder Org-Admin sieht fremde Vorlagen nicht.');
        $this->assertFalse($this->policy->update($foreignAdmin, $template));
        $this->assertFalse($this->policy->view($foreignEditor, $template));
        $this->assertFalse($this->policy->update($foreignEditor, $template));
        $this->assertFalse($this->policy->delete($foreignEditor, $template));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
