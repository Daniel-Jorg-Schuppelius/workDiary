<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeForecastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\User\Permission;
use App\Models\{ScheduledShift, ShiftType, User};
use App\Models\Surcharge\SurchargeRule;
use App\Services\Surcharge\SurchargeForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-533 (Feature 103, Q1-Drittabgleich): Zuschlags-Prognose auf geplante
 * Dienste — Bewertung, Bedingungen, Berechtigung und CSV.
 */
class SurchargeForecastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->viewer = $this->orgUser();
        $this->viewer->givePermissionTo(Permission::ReportView->value);
    }

    private function nightShiftOn(string $date, ?ShiftType $type = null): ScheduledShift {
        $type ??= ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'default_start_time' => '22:00:00',
            'default_end_time' => '06:00:00',
        ]);

        return ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgUser()->id,
            'shift_type_id' => $type->id,
            'date' => $date,
            'status' => ScheduledShiftStatus::Published->value,
        ]);
    }

    public function test_forecast_projects_night_minutes_from_planned_shifts(): void {
        SurchargeRule::factory()->create(['organization_id' => $this->organization->id, 'code' => 'night']);
        $date = CarbonImmutable::now()->startOfMonth()->addDays(9);
        $this->nightShiftOn($date->toDateString());

        $this->actingAs($this->viewer);
        $forecast = app(SurchargeForecastService::class)
            ->forecast((int) $this->organization->id, CarbonImmutable::now(), 3);

        $this->assertCount(1, $forecast['rows']);
        $row = $forecast['rows'][0];
        $this->assertSame('2010', $row['wage_type_code']);
        // Nachtdienst 22–06 mit Fenster 23–06: 60 min am Diensttag + 360 min am Folgetag.
        $this->assertSame(420, $row['total']);
        $this->assertSame(420, array_sum($forecast['totals']));
    }

    public function test_shift_type_condition_restricts_forecast(): void {
        $matching = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'default_start_time' => '22:00:00',
            'default_end_time' => '06:00:00',
        ]);
        $other = ShiftType::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'default_start_time' => '22:00:00',
            'default_end_time' => '06:00:00',
        ]);
        SurchargeRule::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'night',
            'conditions' => ['shift_type_ids' => [$matching->id]],
        ]);

        $date = CarbonImmutable::now()->startOfMonth()->addDays(9)->toDateString();
        $this->nightShiftOn($date, $matching);
        $this->nightShiftOn($date, $other);

        $this->actingAs($this->viewer);
        $forecast = app(SurchargeForecastService::class)
            ->forecast((int) $this->organization->id, CarbonImmutable::now(), 3);

        // Nur der Dienst mit passendem Schichttyp wird bewertet.
        $this->assertSame(420, array_sum($forecast['totals']));
    }

    public function test_report_requires_permission_and_renders(): void {
        $plain = $this->orgUser();

        $this->actingAs($plain)->get(route('reports.surcharge-forecast'))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('reports.surcharge-forecast'))->assertOk();
    }

    public function test_csv_export(): void {
        SurchargeRule::factory()->create(['organization_id' => $this->organization->id, 'code' => 'night']);
        $this->nightShiftOn(CarbonImmutable::now()->startOfMonth()->addDays(9)->toDateString());

        $response = $this->actingAs($this->viewer)
            ->get(route('reports.surcharge-forecast', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('2010', (string) $response->getContent());
    }
}
