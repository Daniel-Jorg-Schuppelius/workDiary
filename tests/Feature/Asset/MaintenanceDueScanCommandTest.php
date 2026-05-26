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

use App\Enums\Asset\MaintenanceIntervalKind;
use App\Models\{Asset, MaintenancePlan, Organization};
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
}
