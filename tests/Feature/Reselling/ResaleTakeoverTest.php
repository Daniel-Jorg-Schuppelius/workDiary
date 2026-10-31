<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleTakeoverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\{HolderTakeover, LicenceAssignments, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Übernahme durch eine andere Firma ab Stichtag (Phase 166, MVP-1451): eine Firma
 * geht insolvent, eine andere führt die Lizenzen weiter.
 */
class ResaleTakeoverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
    }

    private function customer(string $name): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
    }

    /** @param array<string, mixed> $attributes */
    private function contract(Customer $holder, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 3,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $holder->id, 'starts_on' => '2023-08-07', 'ends_on' => '2025-08-06',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_successor_takes_over_from_the_effective_date_and_earlier_open_periods_become_bad_debt(): void {
        $admin = $this->orgAdmin();
        $insolvent = $this->customer('Altbau Technik GmbH');
        $successor = $this->customer('Neubau Service GmbH');
        $contract = $this->contract($insolvent);
        $workspace = route('finance.resale.reconcile.show', $insolvent);
        $this->assertSame(['2023-08-07', '2024-08-07'], $contract->periods()->pluck('starts_on')->map(static fn ($d): string => $d->toDateString())->all());

        // Dialog: Stichtag vorbelegt mit dem Beginn der ersten offenen Periode, offene Perioden aufgelistet.
        $this->actingAs($admin)->get(route('finance.resale.takeover.create', $contract->sqid))->assertOk()
            ->assertSee('value="2023-08-07"', false)->assertSee('07.08.2023 – 06.08.2024')->assertSee(__('resale.takeover.waive_before'));
        $this->actingAs($admin)->get($workspace)->assertOk()->assertSee(route('finance.resale.takeover.create', $contract->sqid), false);

        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.takeover.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2024-08-07', 'waive_before' => '1',
        ])->assertRedirect($workspace)->assertSessionHasNoErrors()->assertSessionHas('success');

        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->assertSame($successor->id, $assignment->customer_id);
        $this->assertSame(3, $assignment->quantity);
        $this->assertSame('2024-08-07', $assignment->starts_on->toDateString());
        $this->assertSame(['2024-08-07'], $assignment->periods()->pluck('starts_on')->map(static fn ($d): string => $d->toDateString())->all());
        $periods = $contract->periods()->get();
        $this->assertCount(1, $periods, 'ab dem Stichtag plant der Vertrag nichts mehr');
        $this->assertSame(PeriodStatus::Waived, $periods->firstOrFail()->status);
        $this->assertStringContainsString('Altbau Technik GmbH', (string) $periods->firstOrFail()->waived_reason);
        $this->assertDatabaseHas('audit_logs', ['event' => HolderTakeover::AUDIT, 'auditable_id' => $contract->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'resale_period.waived', 'auditable_id' => $periods->firstOrFail()->id]);
    }

    public function test_effective_date_at_the_start_moves_the_whole_subscription_and_validation_guards_the_input(): void {
        $admin = $this->orgAdmin();
        $insolvent = $this->customer('Altbau Technik GmbH');
        $successor = $this->customer('Neubau Service GmbH');
        $contract = $this->contract($insolvent);
        $store = route('finance.resale.takeover.store', $contract->sqid);

        $this->actingAs($admin)->post($store, ['mode' => 'customer', 'customer_id' => $insolvent->sqid, 'starts_on' => '2023-08-07'])->assertSessionHasErrors('customer_id');
        $this->actingAs($admin)->post($store, ['mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2025-09-01'])->assertSessionHasErrors('starts_on');

        $this->actingAs($admin)->post($store, ['mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2023-08-07', 'waive_before' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($successor->id, $contract->fresh()?->customer_id);
        $this->assertSame(0, ResaleSubscription::query()->where('parent_id', $contract->id)->count());
        $this->assertSame(0, $contract->periods()->where('status', PeriodStatus::Waived->value)->count(), 'Übernahme ab Beginn: nichts vor dem Stichtag');
    }

    public function test_takeover_of_an_assignment_ends_it_and_starts_a_new_assignment_at_the_contract(): void {
        $admin = $this->orgAdmin();
        $owner = $this->customer('Kontoinhaber GmbH');
        $insolvent = $this->customer('Altbau Technik GmbH');
        $successor = $this->customer('Neubau Service GmbH');
        $contract = $this->contract($owner);
        $assignment = (new LicenceAssignments)->create($contract, ['customer' => $insolvent, 'foreign' => null], 2, '2023-08-07', '2025-08-06', null, null, null);

        $this->actingAs($admin)->post(route('finance.resale.takeover.store', $assignment->sqid), [
            'mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2024-08-07', 'waive_before' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2024-08-06', $assignment->fresh()?->ends_on?->toDateString());
        $next = ResaleSubscription::query()->where('parent_id', $contract->id)->where('customer_id', $successor->id)->firstOrFail();
        $this->assertSame(2, $next->quantity);
        $this->assertSame('2024-08-07', $next->starts_on->toDateString());
        $this->assertSame(PeriodStatus::Open, $assignment->periods()->firstOrFail()->status, 'ohne Haken bleibt die offene Periode beim bisherigen Halter');
        $this->assertSame(1, $contract->fresh()?->billableQuantityOn($next->starts_on), 'der Vertrag rechnet weiter nur die nicht abgetretene Lizenz');
    }
}
