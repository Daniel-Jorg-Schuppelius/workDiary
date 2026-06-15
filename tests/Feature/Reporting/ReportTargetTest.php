<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\User\Permission;
use App\Models\{Customer, Project, ReportTarget, TimeEntry, User};
use App\Services\Reporting\ReportTargetEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class ReportTargetTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_evaluator_marks_target_met_for_higher_is_better(): void {
        $evaluator = app(ReportTargetEvaluator::class);
        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ContributionMargin,
            'scope' => ReportTargetScope::Org,
            'target_value' => 50.0,
        ]);

        $result = $evaluator->compare(ReportTargetMetric::ContributionMargin, 62.0);

        $this->assertNotNull($result);
        $this->assertSame(50.0, $result['target']);
        $this->assertSame(62.0, $result['actual']);
        $this->assertSame(12.0, $result['deviation']);
        $this->assertTrue($result['met']);
        $this->assertSame('success', $result['tone']);
    }

    public function test_evaluator_handles_lower_is_better_and_tones(): void {
        $evaluator = app(ReportTargetEvaluator::class);
        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ReworkShare,
            'scope' => ReportTargetScope::Org,
            'target_value' => 10.0,
        ]);

        // 12 % rework vs. 10 % target → missed but within 5pp → warning.
        $warn = $evaluator->compare(ReportTargetMetric::ReworkShare, 12.0);
        $this->assertNotNull($warn);
        $this->assertFalse($warn['met']);
        $this->assertSame('warning', $warn['tone']);

        // 30 % rework → far off → error.
        $err = $evaluator->compare(ReportTargetMetric::ReworkShare, 30.0);
        $this->assertNotNull($err);
        $this->assertSame('error', $err['tone']);

        // 8 % rework → better than target → met/success.
        $ok = $evaluator->compare(ReportTargetMetric::ReworkShare, 8.0);
        $this->assertNotNull($ok);
        $this->assertTrue($ok['met']);
    }

    public function test_specific_scope_wins_over_org_fallback(): void {
        $evaluator = app(ReportTargetEvaluator::class);
        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ContributionMargin,
            'scope' => ReportTargetScope::Org,
            'target_value' => 50.0,
        ]);
        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ContributionMargin,
            'scope' => ReportTargetScope::Customer,
            'scope_id' => 777,
            'target_value' => 70.0,
        ]);

        $targets = $evaluator->load(ReportTargetMetric::ContributionMargin);
        $resolved = $evaluator->resolve($targets, ReportTargetScope::Customer, 777);
        $this->assertNotNull($resolved);
        $this->assertSame('70.00', (string) $resolved->target_value);

        // Other customer → falls back to org target.
        $fallback = $evaluator->resolve($targets, ReportTargetScope::Customer, 999);
        $this->assertNotNull($fallback);
        $this->assertSame('50.00', (string) $fallback->target_value);
    }

    public function test_admin_can_create_target_via_form(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.report-targets.store'), [
            'metric' => ReportTargetMetric::ContributionMargin->value,
            'scope' => ReportTargetScope::Org->value,
            'target_value' => '55.5',
        ]);

        $response->assertRedirect(route('admin.report-targets.index'));
        $this->assertDatabaseHas('report_targets', [
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ContributionMargin->value,
            'scope' => ReportTargetScope::Org->value,
            'target_value' => 55.5,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_target_management_forbidden_without_permission(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->get(route('admin.report-targets.index'))
            ->assertForbidden();
    }

    public function test_economics_report_shows_soll_ist_deviation(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Zielkunde GmbH',
            'hourly_rate' => 100,
            'internal_rate' => 40,
        ]);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Zielprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => now()->subDays(3)->toDateString(),
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 120,
            'billable' => true,
        ]);

        ReportTarget::factory()->create([
            'organization_id' => $this->organization->id,
            'metric' => ReportTargetMetric::ContributionMargin,
            'scope' => ReportTargetScope::Org,
            'target_value' => 50.0,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.economics'));

        $response->assertOk();
        // Soll-Wert + Soll/Ist-Block werden angezeigt.
        $response->assertSee('50,00');
        $response->assertSee(__('reporting.target.soll'));
        $response->assertSee(__('reporting.target.metric.contributionMargin'));
    }

    public function test_permission_is_mapped_to_geschaeftsfuehrung_role(): void {
        // PermissionsSeeder bildet die Rollen→Permission-Matrix je Organisation
        // ab. Wir fahren ihn für die Test-Org und prüfen die GF-Rolle deterministisch.
        (new \Database\Seeders\PermissionsSeeder)->run();
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($this->organization->id);

        $role = \Spatie\Permission\Models\Role::query()
            ->where('name', \App\Enums\User\UserRole::Geschaeftsfuehrung->value)
            ->where('team_id', $this->organization->id)
            ->first();

        $this->assertNotNull($role);
        $this->assertTrue(
            $role->hasPermissionTo(Permission::ReportTargetManage->value),
            'Geschäftsführung sollte report.target.manage besitzen.',
        );
    }
}
