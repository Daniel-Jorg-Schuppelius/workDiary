<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLifecycleActionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetBlockReason, AssetHealth, AssetOwnership, AssetStatus, DefectSeverity};
use App\Exceptions\AssetNotUsableException;
use App\Models\Asset\{Asset, AssetBlock, MaintenancePlan};
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionSchedule};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Asset\{AssetAssignmentService, AssetBlockService, AssetHealthService, AssetService, AssetUsageGuard};
use App\Services\AssetCompliance\AssetComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1574: Außer Betrieb nehmen, Eigentümerwechsel mit Historie, Status als
 * Sperre im gemeinsamen Modell, Standortwechsel als Ereignis, abgeleiteter
 * Zustand, Rücknahme mit Zustand und Notiz.
 */
class AssetLifecycleActionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function asset(array $attributes = []): Asset {
        return Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value] + $attributes);
    }

    public function test_decommission_dialog_and_action_take_asset_out_of_operation(): void {
        $asset = $this->asset();
        MaintenancePlan::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'code' => 'MP-1',
            'label' => 'Jahreswartung',
            'interval_kind' => 'months',
            'interval_value' => 12,
            'next_due_on' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('assets.decommission.form', $asset))
            ->assertOk()
            ->assertSee(__('asset.decommission.title'));

        $this->actingAs($this->admin)
            ->post(route('assets.decommission', $asset), [
                'decommissioned_on' => now()->toDateString(),
                'decommission_reason' => 'Wirtschaftlicher Totalschaden',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame(AssetStatus::Decommissioned, $asset->status);
        $this->assertSame('Wirtschaftlicher Totalschaden', $asset->decommission_reason);
        $this->assertNotNull($asset->decommissioned_on);
        $this->assertDatabaseHas('maintenance_plans', ['asset_id' => $asset->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'asset.decommissioned', 'auditable_id' => $asset->id]);

        $this->expectException(AssetNotUsableException::class);
        app(AssetUsageGuard::class)->ensureUsable($asset, 'rental');
    }

    public function test_decommission_needs_reason_and_own_permission(): void {
        $asset = $this->asset();

        $this->actingAs($this->admin)
            ->post(route('assets.decommission', $asset), ['decommissioned_on' => now()->toDateString()])
            ->assertSessionHasErrors('decommission_reason');

        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)
            ->post(route('assets.decommission', $asset), ['decommissioned_on' => now()->toDateString(), 'decommission_reason' => 'Defekt'])
            ->assertForbidden();

        $this->assertSame(AssetStatus::Active, $asset->refresh()->status);
    }

    public function test_recommission_returns_the_asset_to_operation_with_reason_and_audit(): void {
        $asset = $this->asset();
        app(AssetService::class)->decommission($asset, $this->admin, now()->toDateString(), 'Gerät vermisst');
        $this->assertSame(AssetStatus::Decommissioned, $asset->refresh()->status);

        // Das Formular führt nicht aus „außer Betrieb“ heraus.
        try {
            app(AssetService::class)->update($asset, $this->admin, ['status' => AssetStatus::Active->value]);
            $this->fail('Formular hat das Asset wieder in Betrieb genommen.');
        } catch (\App\Exceptions\AssetValidationException) {
        }

        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()->assertSee(route('assets.recommission.form', $asset), false);
        $this->actingAs($this->admin)->get(route('assets.recommission.form', $asset))->assertOk()->assertSee(__('asset.recommission.title'));
        $this->actingAs($this->admin)->post(route('assets.recommission', $asset), [])->assertSessionHasErrors('recommission_reason');

        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->post(route('assets.recommission', $asset), ['recommission_reason' => 'Wiedergefunden'])->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('assets.recommission', $asset), ['recommission_reason' => 'Im Lager wiedergefunden'])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame(AssetStatus::Active, $asset->status);
        $this->assertNull($asset->decommissioned_on);
        $this->assertNull($asset->decommission_reason);
        $audit = \App\Models\Audit\AuditLog::query()->where('event', 'asset.recommissioned')->where('auditable_id', $asset->id)->sole();
        $this->assertSame('Im Lager wiedergefunden', $audit->changes['reason']);
        $this->assertSame('Gerät vermisst', $audit->changes['decommission_reason']);
        app(AssetUsageGuard::class)->ensureUsable($asset, 'rental');

        // Ein zweites Mal ist nichts mehr außer Betrieb.
        $this->actingAs($this->admin)->get(route('assets.recommission.form', $asset))->assertNotFound();
        $this->actingAs($this->admin)->post(route('assets.recommission', $asset), ['recommission_reason' => 'Noch einmal'])
            ->assertSessionHasErrors('recommission_reason');
    }

    public function test_checked_out_asset_is_not_decommissioned(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->organization->id]);
        app(AssetAssignmentService::class)->checkOut($asset, $this->admin, $target);

        $this->actingAs($this->admin)
            ->post(route('assets.decommission', $asset), ['decommissioned_on' => now()->toDateString(), 'decommission_reason' => 'Verschrottet'])
            ->assertSessionHasErrors('decommissioned_on');

        $this->assertSame(AssetStatus::LoanOut, $asset->refresh()->status);
    }

    public function test_decommission_suspends_inspection_duties_and_cancels_open_dates(): void {
        $asset = $this->asset();
        $profile = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'dguv_v3',
            'name' => 'Elektroprüfung',
            'inspection_kind' => 'electrical',
            'interval_months' => 12,
            'blocking_mode' => 'warn',
            'is_active' => true,
        ]);
        $assignment = app(AssetComplianceService::class)->assign($profile, $asset, $this->admin);
        $schedule = AssetInspectionSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => 'planned',
        ]);

        app(AssetService::class)->decommission($asset, $this->admin, now()->toDateString(), 'Ausgemustert');

        $this->assertFalse($assignment->refresh()->is_active);
        $this->assertSame('cancelled', $schedule->refresh()->status->value);
        $this->assertNull($asset->refresh()->next_inspection_on);
    }

    public function test_ownership_action_writes_history_and_needs_permission(): void {
        $asset = $this->asset(['owned_by' => 'org', 'customer_id' => null]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('assets.ownership.form', $asset))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('assets.ownership', $asset), [
                'owned_by' => AssetOwnership::Customer->value,
                'customer_id' => $customer->sqid,
                'note' => 'Übergabe nach Kauf',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_ownership_changes', [
            'asset_id' => $asset->id,
            'to_ownership' => 'customer',
            'to_customer_id' => $customer->id,
            'note' => 'Übergabe nach Kauf',
            'changed_by_user_id' => $this->admin->id,
        ]);
        $this->assertSame($customer->id, $asset->refresh()->customer_id);

        // Die Objektakte zeigt die Historie.
        $this->actingAs($this->admin)->get(route('assets.dossier', $asset))->assertOk()->assertSee('Übergabe nach Kauf');

        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)
            ->post(route('assets.ownership', $asset), ['owned_by' => AssetOwnership::Organization->value])
            ->assertForbidden();
    }

    public function test_ownership_action_requires_customer_for_customer_ownership(): void {
        $asset = $this->asset(['owned_by' => 'org', 'customer_id' => null]);

        $this->actingAs($this->admin)
            ->post(route('assets.ownership', $asset), ['owned_by' => AssetOwnership::Customer->value])
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('asset_ownership_changes', 0);
    }

    public function test_status_blocked_creates_one_block_and_leaving_it_releases(): void {
        $asset = $this->asset();
        $service = app(AssetService::class);

        $service->update($asset, $this->admin, ['status' => AssetStatus::Blocked->value]);

        $block = AssetBlock::query()->fromStatus($asset)->active()->sole();
        $this->assertSame(AssetBlockReason::Manual, $block->reason);
        $this->assertFalse(app(AssetUsageGuard::class)->isUsable($asset->refresh(), 'rental'));
        $this->assertFalse(app(AssetUsageGuard::class)->isUsable($asset, 'procedure'));
        $this->assertSame(AssetHealth::Critical, $asset->health);

        $service->update($asset, $this->admin, ['status' => AssetStatus::InRepair->value]);
        $this->assertSame(AssetBlockReason::Maintenance, AssetBlock::query()->fromStatus($asset)->active()->sole()->reason);
        $this->assertSame(AssetStatus::InRepair, $asset->refresh()->status);

        $service->update($asset, $this->admin, ['status' => AssetStatus::Active->value]);
        $this->assertSame(0, AssetBlock::query()->fromStatus($asset)->active()->count());
        $this->assertTrue(app(AssetUsageGuard::class)->isUsable($asset->refresh(), 'rental'));
        $this->assertSame(AssetHealth::Ok, $asset->health);
    }

    public function test_releasing_the_status_block_returns_asset_to_operation(): void {
        $asset = $this->asset();
        app(AssetService::class)->update($asset, $this->admin, ['status' => AssetStatus::Blocked->value]);

        $block = AssetBlock::query()->fromStatus($asset)->active()->sole();
        app(AssetBlockService::class)->release($block, $this->admin, 'Freigabe nach Sichtprüfung');

        $this->assertSame(AssetStatus::Active, $asset->refresh()->status);
    }

    public function test_blocked_status_stops_checkout(): void {
        $asset = $this->asset();
        app(AssetService::class)->update($asset, $this->admin, ['status' => AssetStatus::InRepair->value]);
        $target = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('assets.checkout.store', $asset), ['assigned_to_user_id' => $target->sqid])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_changed_location_is_recorded_as_move(): void {
        $asset = $this->asset(['location_text' => 'Lager Nord']);
        $service = app(AssetService::class);

        $service->update($asset, $this->admin, ['name' => 'Umbenannt', 'location_text' => 'Lager Nord']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'asset.moved', 'auditable_id' => $asset->id]);

        $service->update($asset, $this->admin, ['location_text' => 'Werkstatt Süd']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'asset.moved', 'auditable_id' => $asset->id]);

        $this->actingAs($this->admin)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Lager Nord → Werkstatt Süd');
    }

    public function test_health_follows_defects_and_overdue_dates(): void {
        $asset = $this->asset();
        $assignments = app(AssetAssignmentService::class);

        $defect = $assignments->reportDefect($asset, $this->admin, ['title' => 'Kratzer', 'severity' => DefectSeverity::Low->value]);
        $this->assertSame(AssetHealth::Degraded, $asset->refresh()->health);
        $this->assertDatabaseHas('audit_logs', ['event' => 'asset.healthChanged', 'auditable_id' => $asset->id]);

        $assignments->resolveDefect($defect, $this->admin, 'Poliert');
        $this->assertSame(AssetHealth::Ok, $asset->refresh()->health);

        $asset->forceFill(['next_inspection_on' => now()->subDay()->toDateString()])->saveQuietly();
        $this->assertSame(1, app(AssetHealthService::class)->syncDue());
        $this->assertSame(AssetHealth::Degraded, $asset->refresh()->health);
    }

    public function test_return_dialog_stores_condition_and_note(): void {
        $asset = $this->asset();
        $target = User::factory()->create(['organization_id' => $this->organization->id]);
        $assignment = app(AssetAssignmentService::class)->checkOut($asset, $this->admin, $target, conditionOut: 'neuwertig');

        $this->actingAs($this->admin)
            ->get(route('assets.checkout.return-form', [$asset, $assignment]))
            ->assertOk()
            ->assertSee('neuwertig');

        $this->actingAs($this->admin)
            ->post(route('assets.checkout.return', [$asset, $assignment]), [
                'condition_in' => 'Gehäuse verkratzt',
                'return_note' => 'Akku fehlt',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('asset_assignments', [
            'id' => $assignment->id,
            'condition_in' => 'Gehäuse verkratzt',
            'return_note' => 'Akku fehlt',
        ]);

        $this->actingAs($this->admin)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Gehäuse verkratzt')
            ->assertSee('Akku fehlt');
    }

    public function test_procedure_at_an_order_respects_the_block_of_its_asset(): void {
        app()->instance('currentOrganization', $this->organization);
        $asset = $this->asset();
        app(AssetService::class)->update($asset, $this->admin, ['status' => AssetStatus::Blocked->value]);
        $order = \App\Models\Diary\DiaryEntry::factory()->for($this->admin)->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
        ]);

        $templates = app(\App\Services\Procedure\ProcedureTemplateService::class);
        $template = $templates->create($this->organization, $this->admin, ['code' => 'P7-CHECK', 'name' => 'Funktionsprüfung']);
        $version = $template->versions->first();
        $templates->addStepDef($version, ['code' => 'S1', 'step_type' => \App\Enums\Procedure\ProcedureStepType::Confirm->value, 'label' => 'Prüfen']);
        $templates->publish($version, $this->admin);
        $execution = app(\App\Services\Procedure\ProcedureExecutionService::class);

        try {
            $execution->start($template->fresh(), $order, $this->admin);
            $this->fail('Der Lauf hätte am gesperrten Asset des Auftrags nicht starten dürfen.');
        } catch (AssetNotUsableException) {
            $this->assertDatabaseCount('procedure_runs', 0);
        }

        // Eine Vorlage, die gesperrte Assets erlaubt (etwa die Reparatur), startet.
        $version->forceFill(['allow_blocked' => true])->save();
        $this->assertNotNull($execution->start($template->fresh(), $order, $this->admin)->id);
    }

    public function test_edit_with_another_customer_needs_ownership_permission(): void {
        $asset = $this->asset(['owned_by' => 'org', 'customer_id' => null]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Außendienst darf Assets bearbeiten, aber kein Eigentum übertragen.
        $editor = $this->userWithRole(\App\Enums\User\UserRole::Aussendienst->value);

        $payload = ['asset_class' => 'device', 'name' => $asset->name, 'status' => 'active', 'customer_id' => $customer->id];

        $this->actingAs($editor)
            ->put(route('assets.update', $asset), $payload)
            ->assertSessionHasErrors('customer_id');
        $this->assertNull($asset->refresh()->customer_id);

        $this->actingAs($this->admin)
            ->put(route('assets.update', $asset), $payload + ['ownership_note' => 'Verkauf'])
            ->assertRedirect(route('assets.show', $asset));
        $this->assertDatabaseHas('asset_ownership_changes', ['asset_id' => $asset->id, 'to_customer_id' => $customer->id, 'note' => 'Verkauf']);
    }
}
