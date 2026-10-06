<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProposalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Enums\Investments\{InvestmentCaseStatus, InvestmentOrigin};
use App\Enums\Organization\TenantStatus;
use App\Enums\User\UserRole;
use App\Models\Investments\InvestmentCase;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-936: Investitionsvorschläge aus dem Team und über den öffentlichen Link. */
final class InvestmentProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array<string, string> */
    private function payload(): array {
        return ['title' => 'Neuer Kompressor', 'reason' => 'Der alte Kompressor fällt wöchentlich aus.', 'category' => 'machine', 'urgency' => 'high', 'estimated_amount' => '4500'];
    }

    public function test_employee_without_investment_access_can_propose(): void {
        $employee = $this->userWithRole(UserRole::User->value);
        $this->actingAs($employee)->get(route('investments.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('investments.proposals.create'))->assertOk();
        $this->actingAs($employee)->post(route('investments.proposals.store'), $this->payload())->assertSessionHas('success');

        $case = InvestmentCase::query()->sole();
        $this->assertSame(InvestmentCaseStatus::Idea, $case->status);
        $this->assertSame(InvestmentOrigin::Staff, $case->origin);
        $this->assertSame($employee->id, $case->submitter_user_id);
        $this->assertSame('4500.00', $case->estimated_amount);
    }

    public function test_public_link_needs_release_and_records_the_submitter(): void {
        $this->actingAs($this->admin)->post(route('investments.proposals.rotate'))->assertRedirect(route('investments.proposals.link'));
        $token = session('investment_proposal_token');
        $this->assertIsString($token);
        auth()->logout();
        app()->forgetInstance('currentOrganization');
        $this->get(route('investment-proposal.public', $token))->assertNotFound();

        $this->actingAs($this->admin)->patch(route('investments.proposals.toggle'), ['enabled' => 1]);
        auth()->logout();
        app()->forgetInstance('currentOrganization');
        $this->get(route('investment-proposal.public', $token))->assertOk();
        $this->post(route('investment-proposal.public.store', $token), $this->payload())->assertSessionHasErrors(['submitter_name', 'submitter_email']);
        $this->post(route('investment-proposal.public.store', $token), $this->payload() + ['submitter_name' => 'Standort Nord', 'submitter_email' => 'nord@example.test'])->assertRedirect();

        $case = InvestmentCase::query()->withoutGlobalScopes()->sole();
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->admin->id)->count());
        $this->assertSame(InvestmentOrigin::Public, $case->origin);
        $this->assertSame('Standort Nord', $case->submitter_name);
        $this->assertSame($this->organization->id, $case->organization_id);
        $this->get(route('investment-proposal.public', 'falsch'))->assertNotFound();
    }

    public function test_link_management_needs_manage(): void {
        $employee = $this->userWithRole(UserRole::User->value);
        $this->actingAs($employee)->post(route('investments.proposals.rotate'))->assertForbidden();
    }

    /** Sicherheitsaudit 2026-10-04, pub-3: der Link endet mit der Mandantensperre. */
    public function test_public_link_is_locked_for_a_suspended_tenant(): void {
        $this->actingAs($this->admin)->post(route('investments.proposals.rotate'));
        $token = session('investment_proposal_token');
        $this->actingAs($this->admin)->patch(route('investments.proposals.toggle'), ['enabled' => 1]);
        auth()->logout();
        app()->forgetInstance('currentOrganization');
        $this->get(route('investment-proposal.public', $token))->assertOk();

        $this->organization->forceFill(['tenant_status' => TenantStatus::Suspended])->save();

        $this->get(route('investment-proposal.public', $token))->assertStatus(423);
        $this->post(route('investment-proposal.public.store', $token), $this->payload() + ['submitter_name' => 'Standort Nord', 'submitter_email' => 'nord@example.test'])->assertStatus(423);
    }
}
