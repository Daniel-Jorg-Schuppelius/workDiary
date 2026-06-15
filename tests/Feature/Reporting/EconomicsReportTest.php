<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Reporting\EconomicsReportController;
use App\Models\{AuditLog, Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class EconomicsReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterkunde GmbH',
            'hourly_rate' => 100,
            'internal_rate' => 40,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Wartungsvertrag',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    private function seedWork(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => now()->subDays(3)->toDateString(),
            'kind' => TimeEntryKind::Work->value,
            'minutes' => 120,
            'billable' => true,
        ]);
    }

    private function getWithRange(array $params = []): TestResponse {
        return $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.economics', $params));
    }

    public function test_route_renders_for_admin(): void {
        $this->seedWork();
        $response = $this->getWithRange();
        $response->assertOk();
        $response->assertSee('Musterkunde GmbH');
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.economics'))->assertRedirect(route('login'));
    }

    public function test_forbidden_for_user_without_report_permission(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.economics'))
            ->assertForbidden();
    }

    public function test_csv_export_returns_csv_and_writes_audit_log(): void {
        $this->seedWork();

        $response = $this->getWithRange(['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('Ebene;Name;Kunde', $content);
        $this->assertStringContainsString('Musterkunde GmbH', $content);

        $log = AuditLog::query()->where('event', 'report.exported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(EconomicsReportController::class, $log->auditable_type);
        $this->assertSame('economics', $log->changes['report_code'] ?? null);
        $this->assertSame('csv', $log->changes['format'] ?? null);
    }

    public function test_pdf_export_returns_pdf(): void {
        $this->seedWork();

        $response = $this->getWithRange(['export' => 'pdf']);
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
