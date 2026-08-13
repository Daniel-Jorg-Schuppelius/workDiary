<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvisionalBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Absence;

use App\Enums\Vacation\VacationStatus;
use App\Models\{User, Vacation};
use App\Services\Attendance\EmergencyAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-536 (Feature 103, Q1-Drittabgleich): Vorbehalts-Eintragung — bei
 * aktiver Org-Option wirken beantragte Fehlzeiten sofort in Planungssichten
 * (gekennzeichnet); eine Ablehnung nimmt sie automatisch zurück.
 */
class ProvisionalBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->worker = $this->orgUser();
    }

    private function enableProvisionalBooking(): void {
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, 'vacation.provisional_booking', true);
        $this->organization->update(['settings' => $settings]);
        app()->instance('currentOrganization', $this->organization->fresh());
    }

    private function pendingVacationToday(): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => VacationStatus::Pending->value,
        ]);
    }

    public function test_pending_vacation_is_invisible_without_option(): void {
        $this->pendingVacationToday();

        $this->actingAs($this->worker);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $names = array_map(fn (array $r): string => $r['user']->name, $snapshot['absent']);
        $this->assertNotContains($this->worker->name, $names);
    }

    public function test_pending_vacation_counts_as_absent_with_option(): void {
        $this->enableProvisionalBooking();
        $this->pendingVacationToday();

        $this->actingAs($this->worker);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $names = array_map(fn (array $r): string => $r['user']->name, $snapshot['absent']);
        $this->assertContains($this->worker->name, $names);
    }

    public function test_rejection_removes_provisional_effect(): void {
        $this->enableProvisionalBooking();
        $vacation = $this->pendingVacationToday();
        $vacation->update(['status' => VacationStatus::Rejected->value]);

        $this->actingAs($this->worker);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $names = array_map(fn (array $r): string => $r['user']->name, $snapshot['absent']);
        $this->assertNotContains($this->worker->name, $names);
    }

    public function test_absence_calendar_marks_provisional_entries(): void {
        $this->enableProvisionalBooking();
        $this->pendingVacationToday();
        $viewer = $this->orgAdmin();

        $response = $this->actingAs($viewer)
            ->get(route('reports.absence-calendar', ['year' => now()->year]))
            ->assertOk();

        $response->assertSee((string) __('(Vorbehalt)'));
    }
}
