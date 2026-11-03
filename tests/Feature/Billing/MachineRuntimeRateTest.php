<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MachineRuntimeRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Billing\OrganizationDefaultRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 193: Gerätelaufzeit rechnet mit dem Gerätesatz — ohne Kundenkondition,
 * Personensätze, Anfahrtspauschale und interne Personenkosten.
 */
class MachineRuntimeRateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => 80,
            'internal_rate' => 40,
        ]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => 100,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'billable' => true,
        ]);
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'workdays_per_week' => 6,
            'travel_minutes_per_entry' => 20,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $agreement->id,
            'activity_category_id' => null,
            'day_type' => 'weekday',
            'hourly_rate' => 150,
        ]);
    }

    private function setMachineRate(?float $rate, ?float $defaultRate = null): void {
        $this->organization->update(['settings' => array_replace_recursive(
            (array) $this->organization->settings,
            ['invoicing' => ['machine_hourly_rate' => $rate, 'default_hourly_rate' => $defaultRate]],
        )]);
        app(OrganizationDefaultRateResolver::class)->flush();
    }

    /** @param array<string, mixed> $attributes */
    private function machineEntry(array $attributes = []): TimeEntry {
        return TimeEntry::factory()->machine()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01', // Montag
            'billable' => true,
        ], $attributes));
    }

    public function test_machine_runtime_uses_the_organisation_machine_rate_only(): void {
        $this->setMachineRate(20.0, 90.0);

        $entry = $this->machineEntry();

        // 3097 min = 51,6167 h × 20 € — weder Kondition (150), Person (80), Kunde (100) noch Standardsatz (90).
        $this->assertSame(1032.33, $entry->rate?->toFloat());
        $this->assertSame(0.0, $entry->internal_rate?->toFloat() ?? 0.0);
        $this->assertNull($entry->hourly_rate);
        $this->assertNull($entry->customer_billing_rate_id);
        $this->assertSame(0, (int) $entry->billing_travel_minutes);
    }

    public function test_machine_runtime_without_machine_rate_stays_unpriced(): void {
        $this->setMachineRate(null, 90.0);

        $entry = $this->machineEntry();

        $this->assertSame(0.0, $entry->rate?->toFloat() ?? 0.0);
        $this->assertSame(0.0, $entry->internal_rate?->toFloat() ?? 0.0);
    }

    public function test_entry_rate_overrides_the_machine_rate(): void {
        $this->setMachineRate(20.0);

        $entry = $this->machineEntry(['hourly_rate' => 25]);

        $this->assertSame(1290.42, $entry->rate?->toFloat());
        $this->assertSame(25.0, $entry->hourly_rate?->toFloat());
    }

    public function test_work_entry_still_takes_the_customer_agreement(): void {
        $this->setMachineRate(20.0, 90.0);

        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);

        // 60 min + 20 min Anfahrt × 150 € der Kondition.
        $this->assertSame(200.0, $entry->rate?->toFloat());
        $this->assertSame(40.0, $entry->internal_rate?->toFloat());
    }

    public function test_machine_runtime_without_project_defaults_to_activity_other(): void {
        $entry = TimeEntry::factory()->machine()->create([
            'organization_id' => $this->organization->id,
            'project_id' => null,
            'activity_type' => null,
            'user_id' => $this->user->id,
            'date' => '2030-04-01',
        ]);

        $this->assertSame(TimeEntryActivityType::Other, $entry->activity_type);
    }
}
