<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaAssetBindingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\ServiceTicket\{ServiceQueue, SlaContract};
use App\Services\ServiceTicket\{ServiceTicketService, SlaTimer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1628 (Paket 21g): Die Direktzuordnung am Asset ist die engste Zusage und
 * gilt vor Projekt, Kunde, Queue und Organisation – nie ein inaktiver,
 * projektgebundener Vertrag oder der Vertrag eines anderen Kunden.
 */
class SlaAssetBindingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SlaTimer $timer;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->timer = new SlaTimer;
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_asset_contract_wins_over_project_customer_and_default(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->contract(['is_default' => true]);
        $this->contract(['customer_id' => $this->customer->id]);
        $projectContract = $this->contract(['project_id' => $project->id]);
        $assetContract = $this->contract(['customer_id' => $this->customer->id]);
        $asset = $this->asset($assetContract);

        $this->assertSame($assetContract->id, $this->timer->resolveContract($this->organization->id, $this->customer->id, $project->id, null, $asset->id)?->id);
        // Ohne Asset bleibt die bisherige Reihenfolge.
        $this->assertSame($projectContract->id, $this->timer->resolveContract($this->organization->id, $this->customer->id, $project->id)?->id);
    }

    public function test_inactive_or_project_bound_asset_contract_falls_through(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $customerContract = $this->contract(['customer_id' => $this->customer->id]);

        $inactive = $this->asset($this->contract(['customer_id' => $this->customer->id, 'is_active' => false]));
        $this->assertSame($customerContract->id, $this->timer->resolveContract($this->organization->id, $this->customer->id, assetId: $inactive->id)?->id);

        $projectBound = $this->asset($this->contract(['project_id' => $project->id]));
        $this->assertSame($customerContract->id, $this->timer->resolveContract($this->organization->id, $this->customer->id, assetId: $projectBound->id)?->id);
    }

    public function test_contract_of_another_customer_never_applies_through_the_asset(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $default = $this->contract(['is_default' => true]);
        $asset = $this->asset($this->contract(['customer_id' => $this->customer->id]));

        $this->assertSame($default->id, $this->timer->resolveContract($this->organization->id, $other->id, assetId: $asset->id)?->id);
        $this->assertSame($default->id, $this->timer->resolveContract($this->organization->id, null, assetId: $asset->id)?->id);

        $free = $this->contract();
        $asset->update(['sla_contract_id' => $free->id]);
        $this->assertSame($free->id, $this->timer->resolveContract($this->organization->id, $other->id, assetId: $asset->id)?->id);
    }

    public function test_ticket_at_asset_uses_asset_contract_and_keeps_it_on_queue_change(): void {
        $queueContract = $this->contract();
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Default', 'is_default' => true]);
        $other = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Zweite Ebene', 'default_sla_contract_id' => $queueContract->id]);
        $this->contract(['customer_id' => $this->customer->id]);
        $assetContract = $this->contract(['customer_id' => $this->customer->id]);
        $asset = $this->asset($assetContract);
        $service = app(ServiceTicketService::class);

        $ticket = $service->create($this->organization, null, [
            'title' => 'Kältemaschine fällt aus',
            'priority' => 'normal',
            'customer_id' => $this->customer->id,
            'asset_id' => $asset->id,
        ]);

        $this->assertSame($assetContract->id, $ticket->sla_contract_id);
        $this->assertSame('asset', $ticket->sla_snapshot['source'] ?? null);

        $agent = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $moved = $service->moveToQueue($ticket, $agent, $other);
        $this->assertSame($assetContract->id, $moved->sla_contract_id);
    }

    public function test_explicit_contract_still_wins_over_asset(): void {
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Default', 'is_default' => true]);
        $explicit = $this->contract();
        $asset = $this->asset($this->contract(['customer_id' => $this->customer->id]));

        $ticket = app(ServiceTicketService::class)->create($this->organization, null, [
            'title' => 'Wartung fällig',
            'priority' => 'normal',
            'customer_id' => $this->customer->id,
            'asset_id' => $asset->id,
            'sla_contract_id' => $explicit->id,
        ]);

        $this->assertSame($explicit->id, $ticket->sla_contract_id);
    }

    private function asset(SlaContract $contract): Asset {
        return Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'sla_contract_id' => $contract->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function contract(array $attributes = []): SlaContract {
        return SlaContract::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'is_default' => false,
            'is_active' => true,
        ]);
    }
}
