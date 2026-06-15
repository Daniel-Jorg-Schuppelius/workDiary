<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\{DispatchStatus, Mode, Status};
use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Exceptions\VehicleReservationConflictException;
use App\Models\{DiaryEntry, ScheduledShift, User, Vacation, Vehicle, VehicleReservation};
use App\Services\Dispatch\{DispatchConflictChecker, DispatchStatusResolver, VehicleReservationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DispatchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function admin(): User {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function teamleitung(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function worker(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function setComplianceMode(string $mode): void {
        $this->organization->settings = ['compliance' => ['mode' => $mode]];
        $this->organization->save();
    }

    private function entry(array $overrides = []): DiaryEntry {
        return DiaryEntry::factory()->create(array_replace([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker()->id,
            'mode' => Mode::Fixed->value,
            'status' => Status::Open->value,
            'start_at' => Carbon::parse('2026-07-01 09:00'),
            'end_at' => Carbon::parse('2026-07-01 12:00'),
        ], $overrides));
    }

    // ─── Dispositionsstatus (abgeleitet + explizit) ─────────────────────────

    public function test_dispatch_status_is_unplanned_without_schedule_or_assignment(): void {
        $entry = $this->entry(['start_at' => null, 'end_at' => null, 'assigned_user_id' => null]);

        $this->assertSame(DispatchStatus::Unplanned, app(DispatchStatusResolver::class)->resolve($entry));
    }

    public function test_dispatch_status_is_planned_when_assigned(): void {
        $entry = $this->entry(['assigned_user_id' => $this->worker()->id]);

        $this->assertSame(DispatchStatus::Planned, app(DispatchStatusResolver::class)->resolve($entry));
    }

    public function test_dispatch_status_column_overrides_derivation(): void {
        $entry = $this->entry();
        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Confirmed);

        $fresh = DiaryEntry::findOrFail($entry->id);
        $this->assertSame(DispatchStatus::Confirmed, app(DispatchStatusResolver::class)->resolve($fresh));
        $this->assertNotNull($fresh->getAttribute('dispatch_confirmed_at'));
    }

    public function test_transition_writes_override_audit_trail(): void {
        $entry = $this->entry();
        $actor = $this->admin();

        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Confirmed, $actor->id, 'Kunde wartet dringend');

        $fresh = DiaryEntry::findOrFail($entry->id);
        $this->assertSame('Kunde wartet dringend', $fresh->getAttribute('dispatch_override_reason'));
        $this->assertSame((int) $actor->id, (int) $fresh->getAttribute('dispatch_override_by_user_id'));
    }

    // ─── Konflikterkennung (wiederverwendete Compliance-Regeln) ─────────────

    public function test_no_conflicts_for_clean_assignment(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        $entry = $this->entry(['assigned_user_id' => $u->id]);

        $report = app(DispatchConflictChecker::class)->check($entry);
        $this->assertFalse($report->hasErrors());
        $this->assertEmpty($report->violations);
    }

    public function test_overlap_with_scheduled_shift_is_detected(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '13:00',
            'status' => ScheduledShiftStatus::Published,
        ]);
        $entry = $this->entry(['assigned_user_id' => $u->id]);

        $report = app(DispatchConflictChecker::class)->check($entry);
        $codes = array_map(fn($v) => $v->code, $report->violations);
        $this->assertContains('overlap', $codes);
        $this->assertTrue($report->hasErrors());
    }

    public function test_vacation_conflict_is_detected(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'start_date' => '2026-06-29',
            'end_date' => '2026-07-03',
            'status' => VacationStatus::Approved->value,
        ]);
        $entry = $this->entry(['assigned_user_id' => $u->id]);

        $report = app(DispatchConflictChecker::class)->check($entry);
        $codes = array_map(fn($v) => $v->code, $report->violations);
        $this->assertContains('vacation_conflict', $codes);
    }

    public function test_overlap_with_other_assignment_is_detected(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        $this->entry([
            'assigned_user_id' => $u->id,
            'start_at' => Carbon::parse('2026-07-01 10:00'),
            'end_at' => Carbon::parse('2026-07-01 14:00'),
        ]);
        $entry = $this->entry(['assigned_user_id' => $u->id]);

        $report = app(DispatchConflictChecker::class)->check($entry);
        $codes = array_map(fn($v) => $v->code, $report->violations);
        $this->assertContains('assignment_overlap', $codes);
        $this->assertTrue($report->hasErrors());
    }

    // ─── Konflikt-gesteuerte Bestätigung (HTTP) ─────────────────────────────

    public function test_hard_conflict_blocks_confirmation_without_override(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '13:00',
            'status' => ScheduledShiftStatus::Published,
        ]);
        $entry = $this->entry(['assigned_user_id' => $u->id]);

        $this->actingAs($this->teamleitung())
            ->post(route('dispatch.transition', $entry), ['dispatch_status' => DispatchStatus::Planned->value]);
        $entry->refresh();

        // Erst auf Planned setzen (kein harter Block), dann Confirm versuchen.
        $this->actingAs($this->teamleitung())
            ->from(route('diary.show', $entry))
            ->post(route('dispatch.transition', $entry), ['dispatch_status' => DispatchStatus::Confirmed->value])
            ->assertSessionHasErrors('dispatch');

        $this->assertNotSame(
            DispatchStatus::Confirmed,
            app(DispatchStatusResolver::class)->resolve(DiaryEntry::findOrFail($entry->id)),
        );
    }

    public function test_hard_conflict_can_be_overridden_with_reason(): void {
        $this->setComplianceMode('warn');
        $u = $this->worker();
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $u->id,
            'date' => '2026-07-01',
            'start_time' => '08:00',
            'end_time' => '13:00',
            'status' => ScheduledShiftStatus::Published,
        ]);
        $entry = $this->entry(['assigned_user_id' => $u->id]);
        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Planned);

        $this->actingAs($this->teamleitung())
            ->post(route('dispatch.transition', $entry), [
                'dispatch_status' => DispatchStatus::Confirmed->value,
                'override_reason' => 'Bewusst trotz Schichtkonflikt',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            DispatchStatus::Confirmed,
            app(DispatchStatusResolver::class)->resolve(DiaryEntry::findOrFail($entry->id)),
        );
    }

    public function test_worker_without_dispatch_permission_cannot_transition_others_entry(): void {
        $entry = $this->entry(['user_id' => $this->worker()->id]);
        $stranger = $this->worker();

        $this->actingAs($stranger)
            ->post(route('dispatch.transition', $entry), ['dispatch_status' => DispatchStatus::Planned->value])
            ->assertForbidden();
    }

    // ─── Fahrzeug-Reservierung ──────────────────────────────────────────────

    public function test_vehicle_can_be_reserved(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $user = $this->teamleitung();

        $reservation = app(VehicleReservationService::class)->reserve(
            $vehicle,
            '2026-07-01 09:00',
            '2026-07-01 12:00',
            $user->id,
        );

        $this->assertDatabaseHas('vehicle_reservations', ['id' => $reservation->id]);
    }

    public function test_double_reservation_in_same_window_is_prevented(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $user = $this->teamleitung();
        $service = app(VehicleReservationService::class);

        $service->reserve($vehicle, '2026-07-01 09:00', '2026-07-01 12:00', $user->id);

        $this->expectException(VehicleReservationConflictException::class);
        $service->reserve($vehicle, '2026-07-01 11:00', '2026-07-01 13:00', $user->id);
    }

    public function test_touching_windows_do_not_conflict(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $user = $this->teamleitung();
        $service = app(VehicleReservationService::class);

        $service->reserve($vehicle, '2026-07-01 09:00', '2026-07-01 12:00', $user->id);
        $second = $service->reserve($vehicle, '2026-07-01 12:00', '2026-07-01 14:00', $user->id);

        $this->assertDatabaseHas('vehicle_reservations', ['id' => $second->id]);
    }

    public function test_reservation_endpoint_blocks_double_booking(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $user = $this->teamleitung();
        VehicleReservation::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'reserved_by_user_id' => $user->id,
            'reserved_from' => '2026-07-01 09:00',
            'reserved_to' => '2026-07-01 12:00',
        ]);

        $this->actingAs($user)
            ->post(route('vehicle-reservations.store'), [
                'vehicle_id' => $vehicle->sqid,
                'reserved_from' => '2026-07-01 10:00',
                'reserved_to' => '2026-07-01 13:00',
            ])
            ->assertSessionHasErrors('reserved_from');

        $this->assertSame(1, VehicleReservation::query()->where('vehicle_id', $vehicle->id)->count());
    }

    public function test_worker_without_reserve_permission_cannot_reserve(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->worker())
            ->post(route('vehicle-reservations.store'), [
                'vehicle_id' => $vehicle->sqid,
                'reserved_from' => '2026-07-01 09:00',
                'reserved_to' => '2026-07-01 12:00',
            ])
            ->assertForbidden();
    }
}
