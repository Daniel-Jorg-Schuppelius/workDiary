<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaProjectBindingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Models\{Customer, Project, ServiceQueue, SlaContract, User};
use App\Services\ServiceTicket\{ServiceTicketService, SlaTimer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * W5.4: Projektbindung für SLA-Verträge. Auflösungsreihenfolge
 * Projekt → Kunde → Org-Default; projektgebundene Verträge greifen NUR über
 * ihr Projekt; ohne Projekt bleibt das bisherige Verhalten unverändert.
 */
class SlaProjectBindingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SlaTimer $timer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->timer = new SlaTimer;
    }

    public function test_project_bound_contract_wins_over_customer_contract(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->contract(['is_default' => true]);
        $this->contract(['customer_id' => $customer->id, 'is_default' => false]);
        $projectContract = $this->contract(['project_id' => $project->id, 'is_default' => false]);

        $resolved = $this->timer->resolveContract($this->organization->id, $customer->id, $project->id);

        $this->assertSame($projectContract->id, $resolved?->id);
    }

    public function test_customer_contract_wins_over_default_when_project_unbound(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->contract(['is_default' => true]);
        $customerContract = $this->contract(['customer_id' => $customer->id, 'is_default' => false]);

        // Projekt ohne gebundenen Vertrag → Kunde vor Org-Default.
        $resolved = $this->timer->resolveContract($this->organization->id, $customer->id, $project->id);

        $this->assertSame($customerContract->id, $resolved?->id);
    }

    public function test_without_project_behavior_is_unchanged(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $default = $this->contract(['is_default' => true]);
        $customerContract = $this->contract(['customer_id' => $customer->id, 'is_default' => false]);

        $this->assertSame(
            $customerContract->id,
            $this->timer->resolveContract($this->organization->id, $customer->id)?->id,
        );
        $this->assertSame(
            $default->id,
            $this->timer->resolveContract($this->organization->id, null)?->id,
        );
    }

    public function test_project_bound_contract_never_matches_without_project_context(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $default = $this->contract(['is_default' => true]);
        // Projektgebundener Vertrag MIT Kundenbezug: greift nur über sein Projekt.
        $this->contract(['customer_id' => $customer->id, 'project_id' => $project->id, 'is_default' => false]);

        $this->assertSame(
            $default->id,
            $this->timer->resolveContract($this->organization->id, $customer->id)?->id,
        );
    }

    public function test_ticket_creation_uses_project_bound_contract(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Default', 'is_default' => true]);
        $this->contract(['is_default' => true]);
        $this->contract(['customer_id' => $customer->id, 'is_default' => false]);
        $projectContract = $this->contract(['project_id' => $project->id, 'is_default' => false]);

        $ticket = app(ServiceTicketService::class)->create($this->organization, null, [
            'title' => 'Projektticket',
            'priority' => 'normal',
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame($projectContract->id, $ticket->sla_contract_id);
    }

    public function test_admin_can_store_project_bound_contract(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'PROJ-SLA',
                'label' => 'Projekt-SLA',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480} }',
                'project_id' => (string) $project->id,
            ])->assertRedirect(route('sla-contracts.index'));

        $contract = SlaContract::query()->where('code', 'PROJ-SLA')->firstOrFail();
        $this->assertSame($project->id, $contract->project_id);
    }

    public function test_store_rejects_foreign_project(): void {
        $foreignProject = Project::factory()->create([
            'organization_id' => \App\Models\Organization::factory()->create()->id,
        ]);
        $agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'EVIL-SLA',
                'label' => 'Fremdprojekt',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480} }',
                'project_id' => (string) $foreignProject->id,
            ])->assertSessionHasErrors('project_id');

        $this->assertFalse(SlaContract::query()->where('code', 'EVIL-SLA')->exists());
    }

    /** @param array<string, mixed> $attributes */
    private function contract(array $attributes = []): SlaContract {
        return SlaContract::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
    }
}
