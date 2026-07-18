<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillableTimeAggregatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Invoicing\{BillableTimeAggregator, BillingBlock};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class BillableTimeAggregatorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_ceil_to_increment(): void {
        $agg = new BillableTimeAggregator;

        $this->assertSame(30, $agg->ceilToIncrement(16, 15));
        $this->assertSame(15, $agg->ceilToIncrement(1, 15));
        $this->assertSame(60, $agg->ceilToIncrement(60, 60));
        $this->assertSame(120, $agg->ceilToIncrement(61, 60));
        $this->assertSame(42, $agg->ceilToIncrement(42, 1));
        $this->assertSame(0, $agg->ceilToIncrement(0, 15));
    }

    public function test_bridges_entries_within_gap_into_one_block(): void {
        $project = $this->project(increment: 15, gap: 15);
        // 10:00–10:30 (30) + Lücke 10 + 10:40–11:00 (20) ⇒ raw 60 ⇒ billed 60.
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:30');
        $this->entry($project, '2030-04-01 10:40', '2030-04-01 11:00');

        $blocks = $this->aggregate($project);

        $this->assertCount(1, $blocks);
        $this->assertSame(60, $blocks->first()->rawMinutes);
        $this->assertSame(60, $blocks->first()->billedMinutes);
        $this->assertCount(2, $blocks->first()->entryIds);
    }

    public function test_large_gap_splits_into_two_blocks(): void {
        $project = $this->project(increment: 15, gap: 15);
        // Lücke 20 Min > 15 ⇒ zwei Blöcke, je 30/20 ⇒ aufgerundet 30/30.
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:30');
        $this->entry($project, '2030-04-01 10:50', '2030-04-01 11:10');

        $blocks = $this->aggregate($project)->sortBy(fn(BillingBlock $b) => $b->firstStart->getTimestamp())->values();

        $this->assertCount(2, $blocks);
        $this->assertSame(30, $blocks[0]->billedMinutes);
        $this->assertSame(30, $blocks[1]->billedMinutes);
    }

    public function test_untimed_entries_rounded_individually(): void {
        $project = $this->project(increment: 15, gap: 15);
        $this->entry($project, null, null, minutes: 16);
        $this->entry($project, null, null, minutes: 5);

        $blocks = $this->aggregate($project);

        $this->assertCount(2, $blocks);
        $this->assertEqualsCanonicalizing([30, 15], $blocks->map(fn(BillingBlock $b) => $b->billedMinutes)->all());
    }

    public function test_inherits_increment_from_customer(): void {
        $this->customer->update(['billing_increment_minutes' => 30]);
        $project = $this->project(increment: null, gap: 0);
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:10'); // 10 Min

        $blocks = $this->aggregate($project);

        $this->assertSame(30, $blocks->first()->billedMinutes);
    }

    public function test_inherits_increment_from_org_setting(): void {
        \App\Support\Setting::set('invoicing.billing_increment_minutes', 30, \App\Settings\SettingScope::Organization, $this->organization);
        \App\Support\Setting::set('invoicing.billing_grouping_gap_minutes', 15, \App\Settings\SettingScope::Organization, $this->organization);
        $project = $this->project(increment: null, gap: null);
        // 10 + Lücke 10 + 10 ⇒ ein Block (Org-Lücke 15) ⇒ raw 20 ⇒ getaktet 30.
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:10');
        $this->entry($project, '2030-04-01 10:20', '2030-04-01 10:30');

        $blocks = $this->aggregate($project);

        $this->assertCount(1, $blocks);
        $this->assertSame(30, $blocks->first()->billedMinutes);
    }

    public function test_customer_increment_overrides_org_setting(): void {
        \App\Support\Setting::set('invoicing.billing_increment_minutes', 60, \App\Settings\SettingScope::Organization, $this->organization);
        $this->customer->update(['billing_increment_minutes' => 15]);
        $project = $this->project(increment: null, gap: 0);
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:10'); // 10 Min

        $blocks = $this->aggregate($project);

        $this->assertSame(15, $blocks->first()->billedMinutes);
    }

    public function test_default_is_minute_exact_without_grouping(): void {
        $project = $this->project(increment: null, gap: null);
        $this->entry($project, '2030-04-01 10:00', '2030-04-01 10:07'); // 7 Min
        $this->entry($project, '2030-04-01 10:08', '2030-04-01 10:20'); // 12 Min, 1 Min Lücke

        $blocks = $this->aggregate($project);

        // Keine Taktung, keine Zusammenfassung ⇒ zwei Blöcke, minutengenau.
        $this->assertCount(2, $blocks);
        $this->assertEqualsCanonicalizing([7, 12], $blocks->map(fn(BillingBlock $b) => $b->billedMinutes)->all());
    }

    private function project(?int $increment, ?int $gap): Project {
        return Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web ' . uniqid(),
            'status' => ProjectStatus::Active->value,
            'billing_increment_minutes' => $increment,
            'billing_grouping_gap_minutes' => $gap,
            'created_by' => $this->admin->id,
        ]);
    }

    private function entry(Project $project, ?string $start, ?string $end, ?int $minutes = null): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'started_at' => $start,
            'ended_at' => $end,
            'minutes' => $minutes ?? 0,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);
    }

    /** @return Collection<int, BillingBlock> */
    private function aggregate(Project $project): Collection {
        $entries = TimeEntry::query()
            ->where('project_id', $project->id)
            ->with(['project.parent', 'project.customer'])
            ->get();

        return (new BillableTimeAggregator)->aggregate($entries);
    }
}
