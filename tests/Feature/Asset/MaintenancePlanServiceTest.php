<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\MaintenanceIntervalKind;
use App\Exceptions\AssetValidationException;
use App\Models\{Asset, AuditLog, Organization, User};
use App\Services\Asset\MaintenancePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenancePlanServiceTest extends TestCase {
    use RefreshDatabase;

    private MaintenancePlanService $service;

    private Organization $org;

    private User $actor;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();

        $this->service = new MaintenancePlanService;
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
    }

    public function test_create_generates_code_and_computes_next_due(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $plan = $this->service->create($this->asset, $this->actor, [
            'label' => 'Jahreswartung',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 12,
        ]);

        $this->assertMatchesRegularExpression('/^MP-\d{5}-\d+$/', $plan->code);
        $this->assertSame('2027-06-01', $plan->next_due_on?->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_plan.created',
            'auditable_id' => $plan->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_create_rejects_invalid_kind(): void {
        $this->expectException(AssetValidationException::class);

        $this->service->create($this->asset, $this->actor, [
            'label' => 'X',
            'interval_kind' => 'fortnights',
            'interval_value' => 1,
        ]);
    }

    public function test_mark_completed_reschedules_and_updates_asset_next_maintenance(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $plan = $this->service->create($this->asset, $this->actor, [
            'label' => 'Halbjahr',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 6,
        ]);

        Carbon::setTestNow('2026-07-15 09:00:00');
        $this->service->markCompleted($plan, $this->actor);

        $plan->refresh();
        $this->asset->refresh();

        $this->assertSame('2027-01-15', $plan->next_due_on?->toDateString());
        $this->assertSame('2027-01-15', $this->asset->next_maintenance_on?->toDateString());

        Carbon::setTestNow();
    }

    public function test_pause_and_resume_toggle_active_and_refresh_asset(): void {
        $plan = $this->service->create($this->asset, $this->actor, [
            'label' => 'Quartal',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 3,
        ]);

        $this->asset->refresh();
        $this->assertNotNull($this->asset->next_maintenance_on);

        $this->service->pause($plan, $this->actor);
        $this->asset->refresh();
        $this->assertNull($this->asset->next_maintenance_on);

        $this->service->resume($plan, $this->actor);
        $this->asset->refresh();
        $this->assertNotNull($this->asset->next_maintenance_on);

        $this->assertSame(2, AuditLog::query()
            ->whereIn('event', ['maintenance_plan.paused', 'maintenance_plan.resumed'])
            ->count());
    }

    public function test_operating_hours_interval_yields_null_next_due(): void {
        $plan = $this->service->create($this->asset, $this->actor, [
            'label' => 'Motorstunden',
            'interval_kind' => MaintenanceIntervalKind::OperatingHours->value,
            'interval_value' => 500,
        ]);

        $this->assertNull($plan->next_due_on);
    }
}
