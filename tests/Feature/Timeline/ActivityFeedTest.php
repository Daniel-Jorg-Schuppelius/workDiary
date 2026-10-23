<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Dashboard\Widgets\TeamActivityWidget;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Models\Time\MonthClosure;
use App\Services\Timeline\ActivityFeedService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Aktivitäts-Feed (Feature 183, MVP-1376–1378): je Objekt und organisationsweit, rein lesend. */
class ActivityFeedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin(['name' => 'Ada Admin']);
        $this->actingAs($this->admin);
    }

    /** @return list<string> */
    private function titles(array $feed): array {
        return array_map(static fn ($item): string => $item->title, $feed['items']);
    }

    public function test_subject_feed_merges_audit_and_journal_newest_first(): void {
        $closure = MonthClosure::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'period_year' => 2024, 'period_month' => 1,
            'status' => MonthClosureStatus::Submitted, 'days_total' => 31, 'days_with_attendance' => 0, 'days_closed' => 0, 'days_open' => 0, 'warnings_count' => 0,
        ]);
        Carbon::setTestNow('2030-01-02 10:00:00');
        $closure->record('month.approved', [], $this->admin->id);
        Carbon::setTestNow();

        $titles = $this->titles(app(ActivityFeedService::class)->forSubject($closure));

        $this->assertSame((new MonthClosure)->journal()->getRelated()->newInstance(['event' => 'month.approved'])->label(), $titles[0]);
        $this->assertContains((new AuditLog(['event' => 'created']))->eventLabel(), $titles);
    }

    public function test_generic_update_next_to_a_specific_event_of_the_same_actor_is_noise(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customer->update(['name' => 'Umbenannt']);
        $customer->audit('portal.visibility.updated');

        $titles = $this->titles(app(ActivityFeedService::class)->forSubject($customer));

        $this->assertContains(__('audit-events.portal.visibility.updated'), $titles);
        $this->assertNotContains(__('audit-events.updated'), $titles);
    }

    public function test_generic_update_of_another_actor_stays(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customer->update(['name' => 'Umbenannt']);
        $this->actingAs($this->orgAdmin());
        $customer->audit('portal.visibility.updated');

        $this->assertContains(__('audit-events.updated'), $this->titles(app(ActivityFeedService::class)->forSubject($customer)));
    }

    public function test_reading_the_feed_writes_nothing(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $before = AuditLog::query()->withoutGlobalScopes()->count();
        $last = AuditLog::query()->withoutGlobalScopes()->latest('id')->value('id');

        app(ActivityFeedService::class)->forSubject($supplier);
        $this->get(route('activity.index'))->assertOk();
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee(__('activity.subject_title'));

        $this->assertSame($before, AuditLog::query()->withoutGlobalScopes()->count());
        $this->assertSame($last, AuditLog::query()->withoutGlobalScopes()->latest('id')->value('id'));
    }

    public function test_global_page_follows_the_audit_right_filters_and_organization(): void {
        $mine = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zephyr Eigen']);
        Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $foreign->id]);
        $now = CarbonImmutable::now();

        $all = app(ActivityFeedService::class)->global((int) $this->organization->id, $now->subDay(), $now);
        $customers = app(ActivityFeedService::class)->global((int) $this->organization->id, $now->subDay(), $now, Customer::class);

        $this->assertNotEmpty($all['items']);
        $this->assertCount(1, $customers['items']);
        $this->assertSame(route('customers.show', $mine), $customers['items'][0]->url);
        $this->assertSame([], app(ActivityFeedService::class)->global((int) $this->organization->id, $now->subDay(), $now, actorId: $this->orgUser()->id)['items']);

        $this->get(route('activity.index', ['type' => 'customer']))->assertOk()->assertSee(route('customers.show', $mine), false);
        $this->actingAs($this->orgUser())->get(route('activity.index'))->assertForbidden();
    }

    public function test_team_activity_tile_follows_the_audit_right(): void {
        $widget = app(TeamActivityWidget::class);

        $this->assertTrue($widget->availableFor($this->admin));
        $this->assertFalse($widget->availableFor($this->orgUser()));
        Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertStringContainsString(route('activity.index'), (string) $widget->render($this->admin)->render());
    }
}
