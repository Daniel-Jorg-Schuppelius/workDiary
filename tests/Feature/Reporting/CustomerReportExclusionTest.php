<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerReportExclusionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, TimeEntry, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Feature 002: org-weite Kundenausblendung (customers.exclude_from_reports)
 * in den Auswertungen — Ausblendung in Tabellen UND Charts, temporäres
 * Einbeziehen über include_excluded, Übersteuerung durch explizite Kundenwahl
 * sowie unberührte persönliche Auswertungen (Mein Monat).
 */
class CustomerReportExclusionTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    private Customer $visibleCustomer;

    private Customer $excludedCustomer;

    private Project $visibleProject;

    private Project $excludedProject;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->visibleCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Alpha Sichtbar GmbH',
        ]);
        $this->excludedCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Beta Verborgen AG',
            'exclude_from_reports' => true,
        ]);

        $this->visibleProject = $this->createProject($this->visibleCustomer, 'Projekt Alpha');
        $this->excludedProject = $this->createProject($this->excludedCustomer, 'Projekt Beta');

        // Beide Kunden mit Auftrag + Zeiten (A: 60 min, B: 120 min) im Januar 2030.
        $entryA = $this->createDiaryEntry($this->visibleCustomer, $this->visibleProject);
        $entryB = $this->createDiaryEntry($this->excludedCustomer, $this->excludedProject);
        $this->createTimeEntry($this->visibleProject, $entryA, '09:00:00', '10:00:00');
        $this->createTimeEntry($this->excludedProject, $entryB, '09:00:00', '11:00:00');

        // Offener Punkt am ausgeblendeten Kunden — darf ohne Toggle nirgends auftauchen.
        OpenIssue::create([
            'organization_id' => $this->organization->id,
            'subject_type' => Customer::class,
            'subject_id' => $this->excludedCustomer->id,
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => 'Offener Punkt Verborgen',
            'description' => null,
            'category' => 'customer',
            'severity' => OpenIssueSeverity::High->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => $this->user->id,
            'due_at' => now()->addDay(),
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_customers_report_hides_excluded_customer_in_table_and_charts(): void {
        $response = $this->getWithDateRange('reports.customers');

        $response->assertOk();
        $response->assertSee('Alpha Sichtbar GmbH');
        $response->assertDontSee('Beta Verborgen AG');

        $customerIds = $this->rowCustomerIds($response);
        $this->assertContains($this->visibleCustomer->id, $customerIds);
        $this->assertNotContains($this->excludedCustomer->id, $customerIds);

        $chartLabels = $this->seriesLabels($response, 'customerHoursSeries');
        $this->assertContains('Alpha Sichtbar GmbH', $chartLabels);
        $this->assertNotContains('Beta Verborgen AG', $chartLabels);

        $this->assertNotContains('Beta Verborgen AG', $this->seriesLabels($response, 'openIssuesSeries'));
    }

    public function test_customers_report_shows_excluded_customer_with_include_excluded(): void {
        $response = $this->getWithDateRange('reports.customers', ['include_excluded' => 1]);

        $response->assertOk();
        $response->assertSee('Alpha Sichtbar GmbH');
        $response->assertSee('Beta Verborgen AG');

        $customerIds = $this->rowCustomerIds($response);
        $this->assertContains($this->visibleCustomer->id, $customerIds);
        $this->assertContains($this->excludedCustomer->id, $customerIds);

        $this->assertContains('Beta Verborgen AG', $this->seriesLabels($response, 'customerHoursSeries'));
    }

    public function test_customer_project_report_excludes_hidden_customer_by_default(): void {
        $response = $this->getWithDateRange('reports.customer-project');

        $response->assertOk();
        $bucket = $this->bucketByCustomer($response);
        $this->assertArrayHasKey($this->visibleCustomer->id, $bucket);
        $this->assertArrayNotHasKey($this->excludedCustomer->id, $bucket);
        $this->assertSame(60, $response->viewData('totalMinutes'));
    }

    public function test_explicit_customer_filter_overrides_exclusion(): void {
        $response = $this->getWithDateRange('reports.customer-project', [
            'customer' => Sqid::encode(Customer::class, $this->excludedCustomer->id),
        ]);

        $response->assertOk();
        $bucket = $this->bucketByCustomer($response);
        $this->assertArrayHasKey($this->excludedCustomer->id, $bucket);
        $this->assertArrayNotHasKey($this->visibleCustomer->id, $bucket);
        $this->assertSame(120, $response->viewData('totalMinutes'));
    }

    public function test_my_month_report_still_shows_excluded_customer_times(): void {
        $response = $this->getWithDateRange('reports.my-month');

        $response->assertOk();
        // Persönliche Auswertung bleibt vollständig: 60 (Alpha) + 120 (Beta) Minuten.
        $this->assertSame(180, $response->viewData('monthMinutes'));
    }

    public function test_toggle_only_appears_when_excluded_customers_exist(): void {
        $this->getWithDateRange('reports.customers')
            ->assertOk()
            ->assertSee('Ausgeblendete Kunden einbeziehen');

        $this->excludedCustomer->update(['exclude_from_reports' => false]);

        $this->getWithDateRange('reports.customers')
            ->assertOk()
            ->assertDontSee('Ausgeblendete Kunden einbeziehen');
    }

    /**
     * @param  array<string, int|string>  $parameters
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function getWithDateRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-01-31'))
            ->get(route($routeName, $parameters));
    }

    /**
     * Kundenzeilen (customerId) des Kundenreports.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<mixed>
     */
    private function rowCustomerIds(TestResponse $response): array {
        $rows = $response->viewData('rows');
        $this->assertInstanceOf(Collection::class, $rows);

        return array_values($rows->pluck('customerId')->all());
    }

    /**
     * x-Labels einer Chart-Serie aus den View-Daten.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<mixed>
     */
    private function seriesLabels(TestResponse $response, string $key): array {
        $series = $response->viewData($key);
        $this->assertIsArray($series);

        return array_values(array_map(
            static fn($point) => is_array($point) ? ($point['x'] ?? null) : null,
            $series,
        ));
    }

    /**
     * Kunden-Bucket des Kunden-&-Projekte-Reports (Schlüssel = Kunden-ID).
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return array<int|string, mixed>
     */
    private function bucketByCustomer(TestResponse $response): array {
        $bucket = $response->viewData('bucket');
        $this->assertIsArray($bucket);

        return $bucket;
    }

    private function createProject(Customer $customer, string $name): Project {
        return Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    private function createDiaryEntry(Customer $customer, Project $project): DiaryEntry {
        return DiaryEntry::factory()->for($this->user)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'start_at' => '2030-01-10 09:00:00',
            'end_at' => '2030-01-10 10:00:00',
            'created_at' => '2030-01-10 09:00:00',
        ]);
    }

    private function createTimeEntry(Project $project, DiaryEntry $entry, string $start, string $end): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-10',
            'started_at' => '2030-01-10 ' . $start,
            'ended_at' => '2030-01-10 ' . $end,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
        ]);
    }
}
