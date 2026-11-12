<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceEditingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetCompliance;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\AssetCompliance\{AssetComplianceBlockMode, AssetInspectionScheduleStatus};
use App\Models\Asset\{Asset, AssetBlock};
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile, AssetInspectionEvent, AssetInspectionSchedule};
use App\Models\Platform\User;
use App\Services\AssetCompliance\AssetComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1575: Prüfprofile, Anforderungen und Prüfpflichten bearbeiten und
 * deaktivieren, Intervall je Asset, Termin stornieren, „versäumt“ per Lauf,
 * Roh-Messwerte im Prüfdialog und am Prüfprotokoll.
 */
final class AssetComplianceEditingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Asset $asset;

    private AssetComplianceProfile $profile;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Drehmomentschlüssel']);
        $this->profile = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'torque_check',
            'name' => 'Drehmomentprüfung',
            'inspection_kind' => 'calibration',
            'interval_months' => 12,
            'warn_days_before' => 30,
            'blocking_mode' => AssetComplianceBlockMode::BlockImmediately->value,
            'is_active' => true,
        ]);
    }

    private function assign(array $attributes = []): AssetComplianceAssignment {
        return app(AssetComplianceService::class)->assign($this->profile, $this->asset, $this->admin, $attributes);
    }

    private function schedule(AssetComplianceAssignment $assignment, string $dueOn, ?string $plannedOn = null): AssetInspectionSchedule {
        return AssetInspectionSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $assignment->asset_id,
            'due_on' => $dueOn,
            'planned_on' => $plannedOn,
            'status' => AssetInspectionScheduleStatus::Planned->value,
        ]);
    }

    public function test_profile_can_be_edited_and_deactivated_but_stays_reachable(): void {
        $this->actingAs($this->admin)->get(route('asset-compliance.profiles.edit', $this->profile))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('asset-compliance.profiles.update', $this->profile), [
                'name' => 'Drehmomentprüfung halbjährlich',
                'inspection_kind' => 'calibration',
                'interval_months' => 6,
                'blocking_mode' => AssetComplianceBlockMode::Warn->value,
                'requires_certificate' => '1',
            ])
            ->assertRedirect(route('asset-compliance.profiles.index'));

        $this->profile->refresh();
        $this->assertSame(6, $this->profile->interval_months);
        $this->assertTrue($this->profile->requires_certificate);
        $this->assertSame('torque_check', $this->profile->code);

        $assignment = $this->assign();
        $this->actingAs($this->admin)->post(route('asset-compliance.profiles.toggle', $this->profile))->assertRedirect();
        $this->assertFalse($this->profile->refresh()->is_active);
        // Die Pflicht läuft weiter, das Profil steht unter „deaktiviert“ zur Wiederaufnahme.
        $this->assertTrue($assignment->refresh()->is_active);
        $this->actingAs($this->admin)
            ->get(route('asset-compliance.profiles.index'))
            ->assertOk()
            ->assertSee(__('asset_compliance.profile.inactive_title'))
            ->assertSee('Drehmomentprüfung halbjährlich');

        $this->actingAs($this->admin)->post(route('asset-compliance.profiles.toggle', $this->profile))->assertRedirect();
        $this->assertTrue($this->profile->refresh()->is_active);
    }

    public function test_global_template_cannot_be_edited(): void {
        $global = AssetComplianceProfile::query()->create([
            'organization_id' => null,
            'code' => 'global_template',
            'name' => 'Vorlage',
            'inspection_kind' => 'calibration',
            'interval_months' => 12,
            'blocking_mode' => AssetComplianceBlockMode::Warn->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('asset-compliance.profiles.update', $global), ['name' => 'Geändert', 'inspection_kind' => 'calibration', 'interval_months' => 1, 'blocking_mode' => 'warn'])
            ->assertForbidden();
        $this->actingAs($this->admin)->post(route('asset-compliance.profiles.toggle', $global))->assertForbidden();

        $this->assertSame('Vorlage', $global->refresh()->name);
    }

    public function test_requirement_can_be_edited_and_deactivated(): void {
        $requirement = $this->profile->requirements()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Abweichung',
            'unit' => '%',
            'limit_max' => 4,
        ]);

        $this->actingAs($this->admin)->get(route('asset-compliance.profiles.requirements.edit', [$this->profile, $requirement]))->assertOk();
        $this->actingAs($this->admin)
            ->put(route('asset-compliance.profiles.requirements.update', [$this->profile, $requirement]), ['label' => 'Abweichung vom Sollwert', 'limit_max' => '3.5', 'unit' => '%'])
            ->assertRedirect(route('asset-compliance.profiles.index'));
        $this->assertSame('Abweichung vom Sollwert', $requirement->refresh()->label);
        $this->assertSame('3.5000', $requirement->limit_max);

        $this->actingAs($this->admin)->post(route('asset-compliance.profiles.requirements.toggle', [$this->profile, $requirement]))->assertRedirect();
        $this->assertFalse($requirement->refresh()->is_active);
        $this->assertSame(0, $this->profile->activeRequirements()->count());

        // Fremdes Profil in der URL: die Anforderung gehört nicht dazu.
        $other = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'other',
            'name' => 'Anderes',
            'inspection_kind' => 'calibration',
            'interval_months' => 12,
            'blocking_mode' => 'warn',
            'is_active' => true,
        ]);
        $this->actingAs($this->admin)
            ->post(route('asset-compliance.profiles.requirements.toggle', [$other, $requirement]))
            ->assertNotFound();
    }

    public function test_interval_per_asset_on_assign_and_edit(): void {
        $this->actingAs($this->admin)
            ->post(route('asset-compliance.profiles.assign', $this->profile), [
                'asset_id' => $this->asset->sqid,
                'last_done_on' => '2026-01-15',
                'interval_months_override' => 3,
            ])
            ->assertRedirect();

        $assignment = AssetComplianceAssignment::query()->where('asset_id', $this->asset->id)->sole();
        $this->assertSame(3, $assignment->interval_months_override);
        $this->assertSame('2026-04-15', $assignment->next_due_on->toDateString());

        $this->actingAs($this->admin)->get(route('asset-compliance.assignments.edit', $assignment))->assertOk();
        $this->actingAs($this->admin)
            ->put(route('asset-compliance.assignments.update', $assignment), [
                'interval_months_override' => 6,
                'last_done_on' => '2026-01-15',
                'next_due_on' => '',
                'note' => 'Hersteller verlangt sechs Monate',
            ])
            ->assertRedirect(route('asset-compliance.index'));

        $assignment->refresh();
        $this->assertSame(6, $assignment->interval_months_override);
        $this->assertSame('2026-07-15', $assignment->next_due_on->toDateString());
        $this->assertSame('2026-07-15', $this->asset->refresh()->next_inspection_on->toDateString());
    }

    public function test_deactivating_duty_cancels_dates_and_releases_overdue_block(): void {
        $assignment = $this->assign(['next_due_on' => now()->subMonth()->toDateString()]);
        $schedule = $this->schedule($assignment, now()->addDay()->toDateString());
        app(AssetComplianceService::class)->scanAssignments($this->organization);
        $this->assertSame(1, AssetBlock::query()->where('asset_id', $this->asset->id)->active()->where('reason', AssetBlockReason::InspectionOverdue->value)->count());

        $this->actingAs($this->admin)->post(route('asset-compliance.assignments.toggle', $assignment))->assertRedirect();

        $this->assertFalse($assignment->refresh()->is_active);
        $this->assertSame(AssetInspectionScheduleStatus::Cancelled, $schedule->refresh()->status);
        $this->assertSame(0, AssetBlock::query()->where('asset_id', $this->asset->id)->active()->count());
        $this->assertNull($this->asset->refresh()->next_inspection_on);

        // Ruhende Pflichten bleiben über den Filter erreichbar und lassen sich wieder aufnehmen.
        $this->actingAs($this->admin)
            ->get(route('asset-compliance.index', ['show' => 'inactive']))
            ->assertOk()
            ->assertSee('Drehmomentschlüssel');
        $this->actingAs($this->admin)->post(route('asset-compliance.assignments.toggle', $assignment))->assertRedirect();
        $this->assertTrue($assignment->refresh()->is_active);
    }

    public function test_schedule_can_be_cancelled_with_reason_and_filter_finds_it(): void {
        $schedule = $this->schedule($this->assign(), now()->addWeek()->toDateString());

        $this->actingAs($this->admin)->get(route('asset-compliance.schedules.cancel-form', $schedule))->assertOk();
        $this->actingAs($this->admin)
            ->post(route('asset-compliance.schedules.cancel', $schedule), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($this->admin)
            ->post(route('asset-compliance.schedules.cancel', $schedule), ['cancel_reason' => 'Prüfer erkrankt'])
            ->assertRedirect(route('asset-compliance.schedules.index'));

        $schedule->refresh();
        $this->assertSame(AssetInspectionScheduleStatus::Cancelled, $schedule->status);
        $this->assertSame('Prüfer erkrankt', $schedule->cancel_reason);
        $this->assertDatabaseHas('audit_logs', ['event' => 'assetCompliance.scheduleCancelled', 'auditable_id' => $schedule->id]);

        $this->actingAs($this->admin)
            ->get(route('asset-compliance.schedules.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee('Prüfer erkrankt');

        // Ein stornierter Termin lässt sich nicht noch einmal stornieren.
        $this->actingAs($this->admin)
            ->post(route('asset-compliance.schedules.cancel', $schedule), ['cancel_reason' => 'Noch einmal'])
            ->assertSessionHasErrors('cancel_reason');
    }

    public function test_deadline_scan_marks_passed_dates_as_missed(): void {
        $assignment = $this->assign();
        $passed = $this->schedule($assignment, now()->subDays(3)->toDateString());
        $movedOut = $this->schedule($assignment, now()->subDays(3)->toDateString(), now()->addDays(2)->toDateString());
        $today = $this->schedule($assignment, now()->toDateString());

        app(AssetComplianceService::class)->scanAssignments($this->organization);

        $this->assertSame(AssetInspectionScheduleStatus::Missed, $passed->refresh()->status);
        $this->assertSame(AssetInspectionScheduleStatus::Planned, $movedOut->refresh()->status);
        $this->assertSame(AssetInspectionScheduleStatus::Planned, $today->refresh()->status);

        $this->actingAs($this->admin)
            ->get(route('asset-compliance.schedules.index', ['status' => 'missed']))
            ->assertOk()
            ->assertSee('Drehmomentschlüssel');
    }

    public function test_raw_measurements_are_recorded_and_shown_on_the_report(): void {
        $assignment = $this->assign();

        $this->actingAs($this->admin)
            ->post(route('asset-compliance.inspections.record', $assignment), [
                'result' => 'passed',
                'measurements' => [
                    ['label' => 'Messpunkt 20 Nm', 'value' => '20.4', 'unit' => 'Nm'],
                    ['label' => 'Messpunkt 60 Nm', 'value' => '', 'unit' => 'Nm'],
                ],
            ])
            ->assertRedirect();

        $event = AssetInspectionEvent::query()->where('asset_id', $this->asset->id)->sole();
        $this->assertSame(1, $event->measurements()->count());
        $this->assertDatabaseHas('asset_measurement_values', [
            'asset_inspection_event_id' => $event->id,
            'label' => 'Messpunkt 20 Nm',
            'unit' => 'Nm',
        ]);

        // Ein offener Termin zeigt den Prüfdialog mit der Messreihe.
        $this->schedule($assignment, now()->addMonth()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('asset-compliance.schedules.index'))
            ->assertOk()
            ->assertSee(__('asset_compliance.measurement.title'))
            ->assertSee('Messpunkt 20 Nm')
            ->assertSee('20,4000');
    }
}
