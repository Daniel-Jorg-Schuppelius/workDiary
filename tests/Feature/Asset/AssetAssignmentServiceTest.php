<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetAssignmentServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetStatus, DefectSeverity, DefectStatus};
use App\Exceptions\AssetValidationException;
use App\Models\{Asset, Organization, User};
use App\Services\Asset\AssetAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssetAssignmentServiceTest extends TestCase {
    use RefreshDatabase;

    private AssetAssignmentService $service;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();

        // Vollaudit 2026-07 (H2/H3): Service hat jetzt den AssetUsageGuard als
        // Abhängigkeit — über den Container auflösen statt hart konstruieren.
        $this->service = app(AssetAssignmentService::class);
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->actor = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
    }

    private function asset(array $attributes = []): Asset {
        return Asset::factory()->create(array_merge([
            'organization_id' => $this->org->id,
            'status' => AssetStatus::Active->value,
        ], $attributes));
    }

    public function test_checkout_creates_open_assignment_and_marks_loan_out(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $assignment = $this->service->checkOut($asset, $this->actor, $target);

        $this->assertNull($assignment->returned_at);
        $this->assertSame($target->id, $assignment->assigned_to_user_id);
        $this->assertTrue($this->service->isCheckedOut($asset->refresh()));
        $this->assertSame(AssetStatus::LoanOut, $asset->status);
        $this->assertFalse($this->service->isAvailable($asset));

        $this->assertDatabaseHas('audit_logs', ['event' => 'asset.checkedOut', 'auditable_id' => $asset->id]);
    }

    public function test_double_checkout_is_rejected(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);
        $this->service->checkOut($asset, $this->actor, $target);

        $this->expectException(AssetValidationException::class);
        $this->service->checkOut($asset->refresh(), $this->actor, $target);
    }

    public function test_checkout_requires_a_target(): void {
        $asset = $this->asset();

        $this->expectException(AssetValidationException::class);
        $this->service->checkOut($asset, $this->actor);
    }

    public function test_checkin_releases_the_asset(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = $this->service->checkOut($asset, $this->actor, $target);

        $returned = $this->service->checkIn($assignment, $this->actor, 'ok');

        $this->assertNotNull($returned->returned_at);
        $this->assertSame('ok', $returned->condition_in);
        $this->assertTrue($this->service->isAvailable($asset->refresh()));
        $this->assertSame(AssetStatus::Active, $asset->status);
    }

    public function test_checkin_twice_is_rejected(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = $this->service->checkOut($asset, $this->actor, $target);
        $this->service->checkIn($assignment, $this->actor);

        $this->expectException(AssetValidationException::class);
        $this->service->checkIn($assignment->refresh(), $this->actor);
    }

    public function test_overdue_assignment_is_detected(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);
        $assignment = $this->service->checkOut($asset, $this->actor, $target, null, Carbon::now()->subDay());

        $this->assertTrue($assignment->isOverdue());
    }

    public function test_blocking_defect_prevents_checkout(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $this->service->reportDefect($asset, $this->actor, [
            'severity' => DefectSeverity::High->value,
            'title' => 'Display kaputt',
            'blocks_usage' => true,
        ]);

        $this->assertTrue($this->service->isBlocked($asset->refresh()));
        $this->assertSame(AssetStatus::Blocked, $asset->status);

        $this->expectException(AssetValidationException::class);
        $this->service->checkOut($asset, $this->actor, $target);
    }

    public function test_non_blocking_defect_does_not_prevent_checkout(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $this->service->reportDefect($asset, $this->actor, [
            'severity' => DefectSeverity::Low->value,
            'title' => 'Kratzer',
            'blocks_usage' => false,
        ]);

        $assignment = $this->service->checkOut($asset->refresh(), $this->actor, $target);
        $this->assertNull($assignment->returned_at);
    }

    public function test_resolving_blocking_defect_unblocks_asset(): void {
        $asset = $this->asset();
        $defect = $this->service->reportDefect($asset, $this->actor, [
            'severity' => DefectSeverity::Critical->value,
            'title' => 'Totalausfall',
            'blocks_usage' => true,
        ]);
        $this->assertTrue($this->service->isBlocked($asset->refresh()));

        $resolved = $this->service->resolveDefect($defect, $this->actor, 'Repariert');

        $this->assertSame(DefectStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertFalse($this->service->isBlocked($asset->refresh()));
        $this->assertSame(AssetStatus::Active, $asset->status);
    }

    public function test_resolve_requires_note(): void {
        $asset = $this->asset();
        $defect = $this->service->reportDefect($asset, $this->actor, [
            'severity' => DefectSeverity::High->value,
            'title' => 'X',
            'blocks_usage' => true,
        ]);

        $this->expectException(AssetValidationException::class);
        $this->service->resolveDefect($defect, $this->actor, '   ');
    }

    public function test_defect_state_machine_rejects_invalid_transition(): void {
        $asset = $this->asset();
        $defect = $this->service->reportDefect($asset, $this->actor, [
            'severity' => DefectSeverity::Medium->value,
            'title' => 'Y',
            'blocks_usage' => false,
        ]);
        $this->service->writeOff($defect, $this->actor, 'Ausgebucht');

        // writtenOff ist terminal — kein Übergang nach inRepair erlaubt.
        $this->expectException(AssetValidationException::class);
        $this->service->markInRepair($defect->refresh(), $this->actor);
    }

    public function test_decommissioned_asset_cannot_be_checked_out(): void {
        $asset = $this->asset(['status' => AssetStatus::Decommissioned->value, 'decommissioned_on' => now()->toDateString()]);
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $this->expectException(AssetValidationException::class);
        $this->service->checkOut($asset, $this->actor, $target);
    }
}
