<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceDueScanCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{MaintenanceDueAction, MaintenanceIntervalKind};
use App\Models\{Asset, MaintenancePlan, Organization, SlaContract};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceDueScanCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_command_audits_overdue_plans_only(): void {
        Carbon::setTestNow('2026-06-01 00:00:00');

        $org = Organization::factory()->create();
        $asset = Asset::factory()->create(['organization_id' => $org->id]);

        $overdue = MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-OVERDUE',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 6,
            'tolerance_days' => 0,
            'next_due_on' => '2026-05-15',
            'is_active' => true,
        ]);

        $future = MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-FUTURE',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 6,
            'tolerance_days' => 0,
            'next_due_on' => '2027-01-01',
            'is_active' => true,
        ]);

        $inactive = MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-PAUSED',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 6,
            'tolerance_days' => 0,
            'next_due_on' => '2026-05-15',
            'is_active' => false,
        ]);

        $this->artisan('maintenance:scan-due')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_plan.due_detected',
            'auditable_id' => $overdue->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'maintenance_plan.due_detected',
            'auditable_id' => $future->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'maintenance_plan.due_detected',
            'auditable_id' => $inactive->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_due_plan_with_ticket_action_creates_bound_ticket_idempotently(): void {
        Carbon::setTestNow('2026-06-01 00:00:00');

        $org = Organization::factory()->create();
        $asset = Asset::factory()->create(['organization_id' => $org->id]);
        $contract = SlaContract::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => null,
            'is_default' => true,
            'is_active' => true,
        ]);

        MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-DUE',
            'tolerance_days' => 0,
            'next_due_on' => '2026-05-15',
            'is_active' => true,
            'due_action' => MaintenanceDueAction::Ticket->value,
            'sla_contract_id' => $contract->id,
            'is_contractual' => true,
        ]);

        // Zwei Läufe → nur EIN Ticket (idempotent je Fälligkeit).
        $this->artisan('maintenance:scan-due')->assertSuccessful();
        $this->artisan('maintenance:scan-due')->assertSuccessful();

        $this->assertDatabaseCount('service_tickets', 1);
        $this->assertDatabaseHas('service_tickets', [
            'organization_id' => $org->id,
            'source' => 'maintenance_plan',
            'source_reference' => 'MP-DUE',
            'asset_id' => $asset->id,
            'sla_contract_id' => $contract->id,   // Vertragsbindung
            'reported_by_user_id' => null,         // systemgeneriert
        ]);

        Carbon::setTestNow();
    }

    public function test_due_action_none_creates_no_ticket(): void {
        Carbon::setTestNow('2026-06-01 00:00:00');

        $org = Organization::factory()->create();
        $asset = Asset::factory()->create(['organization_id' => $org->id]);
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-AUDIT-ONLY',
            'tolerance_days' => 0,
            'next_due_on' => '2026-05-15',
            'is_active' => true,
            'due_action' => MaintenanceDueAction::None->value,
        ]);

        $this->artisan('maintenance:scan-due')->assertSuccessful();

        $this->assertDatabaseCount('service_tickets', 0);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_plan.due_detected',
            'auditable_id' => $plan->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_not_due_ticket_plan_creates_nothing(): void {
        Carbon::setTestNow('2026-06-01 00:00:00');

        $org = Organization::factory()->create();
        $asset = Asset::factory()->create(['organization_id' => $org->id]);
        MaintenancePlan::factory()->create([
            'organization_id' => $org->id,
            'asset_id' => $asset->id,
            'code' => 'MP-LATER',
            'tolerance_days' => 0,
            'next_due_on' => '2027-01-01',
            'is_active' => true,
            'due_action' => MaintenanceDueAction::Ticket->value,
        ]);

        $this->artisan('maintenance:scan-due')->assertSuccessful();

        $this->assertDatabaseCount('service_tickets', 0);

        Carbon::setTestNow();
    }
}
