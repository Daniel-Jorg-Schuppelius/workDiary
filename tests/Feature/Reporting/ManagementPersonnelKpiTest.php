<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementPersonnelKpiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\User\{Permission, UserRole};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use App\Services\Reporting\{ManagementOverviewService, SicknessReportBuilder};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Personalbereich der Leitungssicht (MVP-1617, F9-06). */
class ManagementPersonnelKpiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->setUpOrganization();
        $this->worker = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Wanda Werker']);

        SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-06']);
        $expired = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ersthelfer']);
        $expiring = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Staplerschein']);
        $this->worker->qualifications()->attach($expired->id, ['valid_until' => '2026-11-01']);
        $this->worker->qualifications()->attach($expiring->id, ['valid_until' => '2026-12-05']);
        Vacation::create([
            'organization_id' => $this->organization->id, 'user_id' => $this->worker->id, 'start_date' => '2026-11-25', 'end_date' => '2026-11-27',
            'type' => VacationType::Vacation->value, 'status' => VacationStatus::Pending->value,
        ]);
    }

    /** @return array<string, list<\App\Services\Reporting\Dto\ManagementKpi>> */
    private function overview(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $now = CarbonImmutable::now();

        return app(ManagementOverviewService::class)->build($viewer, $this->organization, $now->startOfMonth(), $now);
    }

    public function test_hr_group_matches_the_sickness_and_qualification_reports(): void {
        $hr = $this->overview($this->orgAdmin())['hr'] ?? [];
        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now();
        $builder = app(SicknessReportBuilder::class);
        $sickDays = $builder->totals($builder->rows($from, $to, $builder->leaves($from, $to)))['sick_workdays'];

        $this->assertSame([
            __('reporting.management.kpi.sick_days') => (string) $sickDays,
            __('reporting.management.kpi.qualifications_expired') => '1',
            __('reporting.management.kpi.vacation_pending') => '1',
        ], array_combine(array_map(static fn ($k): string => $k->label, $hr), array_map(static fn ($k): string => $k->value, $hr)));
        $this->assertGreaterThan(0, $sickDays);
        $this->assertSame(__('reporting.management.kpi.qualifications_expiring', ['count' => 1, 'days' => 30]), $hr[1]->hint);
        $this->assertSame(__('reporting.management.kpi.vacation_pending_hint', ['count' => 1]), $hr[2]->hint);
        $this->assertSame(route('reports.sickness', ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $hr[0]->url);
    }

    public function test_each_kpi_needs_the_right_of_its_report(): void {
        $viewer = $this->orgUser();
        $viewer->givePermissionTo([Permission::ReportView->value, Permission::SickLeaveViewAny->value]);
        $viewer->unsetRelation('permissions');

        $hr = $this->overview($viewer)['hr'] ?? [];

        $this->assertSame([__('reporting.management.kpi.sick_days')], array_map(static fn ($k): string => $k->label, $hr));
        $this->assertArrayNotHasKey('hr', $this->overview($this->worker));
    }

    public function test_page_and_pdf_show_the_hr_group(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('reports.management'))
            ->assertOk()
            ->assertSee(__('reporting.management.group.hr'))
            ->assertSee(__('reporting.management.kpi.qualifications_expired'));
        $this->actingAs($admin)->get(route('reports.management.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
