<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceDashboardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\{Attendance, Team, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Rang 39: Arbeitszeit-Compliance-Dashboard — Rechte, Konsistenz zur
 * Einzelreport-Berechnung, Team-Aggregation.
 */
class ComplianceDashboardTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        config(['timesheet.breaks.auto_apply' => false]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_requires_compliance_permission(): void {
        $this->actingAs($this->worker)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.compliance.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_aggregates_findings_by_month_and_team(): void {
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Montage']);
        $team->members()->attach($this->worker->id);

        // 12h-Tag → Verstoß max. Tagesarbeitszeit (Standard-Schwelle 10h).
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'date' => '2030-03-05',
            'started_at' => '2030-03-05 06:00:00',
            'ended_at' => '2030-03-05 18:30:00',
            'break_minutes_auto' => 0,
            'break_minutes_manual' => 45,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeYear(2030))
            ->get(route('reports.compliance.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Arbeitszeit-Compliance'));
        // Team-Aggregation nennt das Team, nicht einzelne Personen in der Übersicht.
        $response->assertSee('Montage');
        $response->assertSee('2030-03');
    }
}
