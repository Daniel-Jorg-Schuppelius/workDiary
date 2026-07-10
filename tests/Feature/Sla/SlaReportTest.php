<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Enums\ServiceTicket\SlaViolationKind;
use App\Models\{Customer, Project, ServiceTicket, SlaContract, SlaContractQuota, SlaViolation, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class SlaReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function seedData(): void {
        Carbon::setTestNow('2026-06-10 12:00:00');
        // Drei Tickets mit Lösungsfrist im Zeitraum; zwei mit Verletzung.
        $t1 = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'reported_at' => '2026-06-05 09:00:00',
            'resolution_due_at' => '2026-06-05 17:00:00',
            'priority' => 'high',
        ]);
        $t2 = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'reported_at' => '2026-06-06 09:00:00',
            'resolution_due_at' => '2026-06-06 17:00:00',
            'priority' => 'normal',
        ]);
        ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'reported_at' => '2026-06-07 09:00:00',
            'resolution_due_at' => '2026-06-07 17:00:00',
            'priority' => 'low',
        ]);

        SlaViolation::factory()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $t1->id,
            'kind' => SlaViolationKind::ResolutionTime->value,
            'breached_at' => '2026-06-05 18:00:00',
            'priority' => 'high',
        ]);
        SlaViolation::factory()->responseTime()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $t2->id,
            'breached_at' => '2026-06-06 18:00:00',
            'priority' => 'normal',
        ]);
    }

    public function test_report_renders_with_compliance_metrics(): void {
        $this->seedData();

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.sla'));

        $response->assertOk();
        $response->assertViewHas('violation_count', 2);
        $response->assertViewHas('total_tickets', 3);
        // Einhaltungsquote = (3 - 2) / 3.
        $response->assertViewHas('compliance_rate', fn($rate) => abs($rate - (1 / 3)) < 0.001);
        $byKind = $response->viewData('by_kind');
        $this->assertSame(1, $byKind[SlaViolationKind::ResolutionTime->value]);
        $this->assertSame(1, $byKind[SlaViolationKind::ResponseTime->value]);

        Carbon::setTestNow();
    }

    public function test_report_shows_quota_usage(): void {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $contract = SlaContract::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'is_active' => true,
            'is_default' => false,
        ]);
        SlaContractQuota::query()->create([
            'organization_id' => $this->organization->id,
            'sla_contract_id' => $contract->id,
            'period_kind' => 'month',
            'included_minutes' => 600,
            'warn_threshold_pct' => 80,
        ]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        foreach ([300, 200] as $minutes) {
            TimeEntry::factory()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $worker->id,
                'project_id' => $project->id,
                'activity_type' => 'project',
                'date' => '2026-06-05',
                'minutes' => $minutes,
                'billable' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.sla'));

        $response->assertOk();
        $quotas = $response->viewData('quotas');
        $this->assertCount(1, $quotas);
        $this->assertSame(500, $quotas[0]['consumed']);   // 300 + 200 (Projekt-verknüpft, billable, in Periode)
        $this->assertSame(600, $quotas[0]['included']);
        $this->assertSame(83, $quotas[0]['percentage']);
        $this->assertTrue($quotas[0]['threshold_reached']);
        $response->assertSee(__('sla.report.quotas_heading'));

        Carbon::setTestNow();
    }

    public function test_csv_export_returns_attachment(): void {
        $this->seedData();

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.sla', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        Carbon::setTestNow();
    }

    public function test_user_without_permission_is_forbidden(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.sla'))
            ->assertForbidden();
    }

    public function test_acknowledge_requires_manage_permission(): void {
        $this->seedData();
        $violation = SlaViolation::query()->firstOrFail();

        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)
            ->post(route('reports.sla.acknowledge', $violation), ['cause' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('reports.sla.acknowledge', $violation), ['cause' => 'Materialengpass'])
            ->assertRedirect(route('reports.sla'));

        $this->assertNotNull($violation->refresh()->acknowledged_at);
        $this->assertSame('Materialengpass', $violation->cause);

        Carbon::setTestNow();
    }
}
