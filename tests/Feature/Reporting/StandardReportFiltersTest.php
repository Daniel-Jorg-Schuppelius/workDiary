<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StandardReportFiltersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters;
use App\Models\{Customer, EntryType, Organization, Project, Team, TimeEntry, User};
use App\Services\Reporting\ReportFilters;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Standard-Filterset der Auswertungen (Feature 002): Sqid-Auflösung,
 * Org-Scoping (Cross-Tenant-IDs werden still verworfen), Kunde↔Projekt-
 * Konsistenz, Status-Whitelist sowie die apply*-Helfer und der
 * Query-Param-Roundtrip für Drilldowns.
 */
class StandardReportFiltersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private object $harness;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        $this->customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Kunde A']);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Projekt A',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);

        $this->harness = new class {
            use ResolvesStandardReportFilters {
                standardFilters as public;
                standardFilterOptions as public;
            }
        };
    }

    private function resolve(array $query, array $fields, array $statusValues = []): ReportFilters {
        return $this->harness->standardFilters(
            Request::create('/reports/test', 'GET', $query),
            $fields,
            CarbonImmutable::parse('2030-01-01'),
            CarbonImmutable::parse('2030-01-31'),
            $statusValues,
        );
    }

    public function test_resolves_valid_sqid_filters(): void {
        $filters = $this->resolve([
            'customer' => Sqid::encode(Customer::class, $this->customer->id),
            'project' => Sqid::encode(Project::class, $this->project->id),
            'user' => Sqid::encode(User::class, $this->user->id),
        ], ['customer', 'project', 'user']);

        $this->assertSame($this->customer->id, $filters->customerId);
        $this->assertSame($this->project->id, $filters->projectId);
        $this->assertSame($this->user->id, $filters->userId);
        $this->assertSame(3, $filters->activeCount());
    }

    public function test_rejects_cross_org_ids_silently(): void {
        $other = Organization::factory()->create();
        $foreignCustomer = Customer::create(['organization_id' => $other->id, 'name' => 'Fremd']);
        $foreignUser = User::factory()->create(['organization_id' => $other->id]);
        $foreignTeam = Team::create(['organization_id' => $other->id, 'name' => 'Fremd-Team', 'slug' => 'fremd-team']);

        $filters = $this->resolve([
            'customer' => Sqid::encode(Customer::class, $foreignCustomer->id),
            'user' => Sqid::encode(User::class, $foreignUser->id),
            'team' => Sqid::encode(Team::class, $foreignTeam->id),
        ], ['customer', 'user', 'team']);

        $this->assertNull($filters->customerId);
        $this->assertNull($filters->userId);
        $this->assertNull($filters->teamId);
        $this->assertSame(0, $filters->activeCount());
    }

    public function test_rejects_project_not_belonging_to_selected_customer(): void {
        $otherCustomer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Kunde B']);

        $filters = $this->resolve([
            'customer' => Sqid::encode(Customer::class, $otherCustomer->id),
            'project' => Sqid::encode(Project::class, $this->project->id),
        ], ['customer', 'project']);

        $this->assertSame($otherCustomer->id, $filters->customerId);
        $this->assertNull($filters->projectId);
    }

    public function test_status_is_validated_against_whitelist(): void {
        $valid = $this->resolve(['status' => 'open'], ['status'], ['open', 'done']);
        $invalid = $this->resolve(['status' => 'evil'], ['status'], ['open', 'done']);

        $this->assertSame('open', $valid->status);
        $this->assertNull($invalid->status);
    }

    public function test_ignores_fields_that_are_not_enabled(): void {
        $filters = $this->resolve([
            'customer' => Sqid::encode(Customer::class, $this->customer->id),
        ], ['user']);

        $this->assertNull($filters->customerId);
    }

    public function test_apply_to_time_entry_query_filters_by_customer_user_and_team(): void {
        $secondUser = $this->orgUser();
        $team = Team::create(['organization_id' => $this->organization->id, 'name' => 'Team A', 'slug' => 'team-a']);
        $team->members()->attach($this->user->id);

        $otherCustomer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Kunde B']);
        $otherProject = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'name' => 'Projekt B',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);

        foreach ([[$this->project, $this->user], [$otherProject, $this->user], [$this->project, $secondUser]] as [$project, $user]) {
            TimeEntry::create([
                'organization_id' => $this->organization->id,
                'project_id' => $project->id,
                'user_id' => $user->id,
                'date' => '2030-01-10',
                'started_at' => '2030-01-10 09:00:00',
                'ended_at' => '2030-01-10 10:00:00',
                'kind' => TimeEntryKind::Work->value,
            ]);
        }

        $byCustomer = new ReportFilters(from: CarbonImmutable::parse('2030-01-01'), to: CarbonImmutable::parse('2030-01-31'), customerId: $this->customer->id);
        $this->assertSame(2, $byCustomer->applyToTimeEntryQuery(TimeEntry::query())->count());

        $byUser = new ReportFilters(from: CarbonImmutable::parse('2030-01-01'), to: CarbonImmutable::parse('2030-01-31'), userId: $secondUser->id);
        $this->assertSame(1, $byUser->applyToTimeEntryQuery(TimeEntry::query())->count());

        $byTeam = new ReportFilters(from: CarbonImmutable::parse('2030-01-01'), to: CarbonImmutable::parse('2030-01-31'), teamId: $team->id);
        $this->assertSame(2, $byTeam->applyToTimeEntryQuery(TimeEntry::query())->count());
        $this->assertSame([$this->user->id], $byTeam->teamUserIds());
    }

    public function test_query_param_roundtrip_preserves_filters(): void {
        $original = $this->resolve([
            'customer' => Sqid::encode(Customer::class, $this->customer->id),
            'project' => Sqid::encode(Project::class, $this->project->id),
        ], ['customer', 'project']);

        $params = $original->toQueryParams();
        $this->assertSame('2030-01-01', $params['from']);
        $this->assertSame('2030-01-31', $params['to']);

        $again = $this->resolve($params, ['customer', 'project']);
        $this->assertSame($original->customerId, $again->customerId);
        $this->assertSame($original->projectId, $again->projectId);
    }

    public function test_filter_options_are_org_scoped_and_customer_dependent(): void {
        $other = Organization::factory()->create();
        Customer::create(['organization_id' => $other->id, 'name' => 'Fremdkunde']);
        $otherCustomer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Kunde B']);
        EntryType::create(['organization_id' => $this->organization->id, 'slug' => 'wartung', 'label' => 'Wartung', 'is_active' => true]);

        $filters = $this->resolve(['customer' => Sqid::encode(Customer::class, $this->customer->id)], ['customer', 'project']);
        $options = $this->harness->standardFilterOptions(['customer', 'project', 'entry_type'], $filters);

        $this->assertEqualsCanonicalizing(
            [$this->customer->id, $otherCustomer->id],
            $options['filterCustomers']->pluck('id')->all(),
        );
        // Projektliste folgt dem gewählten Kunden (inkl. dessen automatisch
        // angelegtem Default-Projekt, s. CustomerObserver) — Projekte anderer
        // Kunden bleiben außen vor.
        $projectIds = $options['filterProjects']->pluck('id');
        $this->assertTrue($projectIds->contains($this->project->id));
        $this->assertEqualsCanonicalizing(
            Project::query()->where('customer_id', $this->customer->id)->pluck('id')->all(),
            $projectIds->all(),
        );
        $this->assertSame(['Wartung'], $options['filterEntryTypes']->pluck('label')->all());
    }

    public function test_audit_array_contains_only_active_filters(): void {
        $filters = new ReportFilters(
            from: CarbonImmutable::parse('2030-01-01'),
            to: CarbonImmutable::parse('2030-01-31'),
            customerId: 7,
            scope: 'team',
        );

        $this->assertSame([
            'from' => '2030-01-01',
            'to' => '2030-01-31',
            'customer_id' => 7,
            'scope' => 'team',
        ], $filters->toAuditArray());
    }
}
