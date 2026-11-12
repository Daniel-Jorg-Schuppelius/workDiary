<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueDueFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\WorkQueue;

use App\Enums\User\UserRole;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Enums\WorkQueue\WorkItemDueFilter;
use App\Models\Absence\Vacation;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Task;
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Models\FireflyTransaction;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Filter „Fälligkeit“ und Firefly-Bewegungen im Arbeitsvorrat (MVP-1617, F9-01/F9-05). */
class WorkQueueDueFilterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        // Mittwoch; „diese Woche“ reicht bis Sonntag, den 15.
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->setUpOrganization();
    }

    private function vacation(string $name, string $start): void {
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->userWithRole(UserRole::User->value, attributes: ['name' => $name])->id,
            'start_date' => $start,
            'end_date' => $start,
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }

    public function test_due_filter_narrows_the_page(): void {
        $approver = $this->orgAdmin();
        $this->vacation('Olga Überfällig', '2026-11-09');
        $this->vacation('Hugo Heute', '2026-11-11');
        $this->vacation('Willi Woche', '2026-11-15');
        $this->vacation('Sepp Später', '2026-11-16');
        Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $approver->id, 'title' => 'Aufgabe ohne Frist', 'due_date' => null]);

        $page = fn (string $due) => $this->actingAs($approver)->get(route('work-queue.index', ['due' => $due]))->assertOk();

        $page(WorkItemDueFilter::Overdue->value)->assertSee('Olga Überfällig')->assertDontSee('Hugo Heute')->assertDontSee('Aufgabe ohne Frist');
        $page(WorkItemDueFilter::Today->value)->assertSee('Hugo Heute')->assertDontSee('Olga Überfällig')->assertDontSee('Willi Woche');
        $page(WorkItemDueFilter::Week->value)->assertSee('Hugo Heute')->assertSee('Willi Woche')->assertDontSee('Sepp Später')->assertDontSee('Olga Überfällig');
        $page(WorkItemDueFilter::None->value)->assertSee('Aufgabe ohne Frist')->assertDontSee('Hugo Heute');
        $page('unbekannt')->assertSee('Sepp Später')->assertSee('Aufgabe ohne Frist');
    }

    public function test_due_filter_cases(): void {
        $now = CarbonImmutable::parse('2026-11-11 10:00:00');

        $this->assertTrue(WorkItemDueFilter::Overdue->matches($now->subDay(), $now));
        $this->assertFalse(WorkItemDueFilter::Overdue->matches($now->startOfDay(), $now));
        $this->assertTrue(WorkItemDueFilter::Today->matches($now->endOfDay(), $now));
        $this->assertTrue(WorkItemDueFilter::Week->matches(CarbonImmutable::parse('2026-11-15 23:00:00'), $now));
        $this->assertFalse(WorkItemDueFilter::Week->matches(CarbonImmutable::parse('2026-11-16 00:00:00'), $now));
        $this->assertTrue(WorkItemDueFilter::None->matches(null, $now));
        $this->assertFalse(WorkItemDueFilter::Week->matches(null, $now));
    }

    public function test_unassigned_firefly_transactions_are_bundled_for_admins_with_the_plugin(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => FireflyPlugin::ID, 'enabled' => true, 'settings' => []]);
        foreach ([['1', FireflyMatchStatus::Unmatched], ['2', FireflyMatchStatus::Unmatched], ['3', FireflyMatchStatus::Ambiguous], ['4', FireflyMatchStatus::Matched]] as [$id, $status]) {
            FireflyTransaction::query()->forceCreate([
                'organization_id' => $this->organization->id, 'remote_journal_id' => $id, 'kind' => FireflyTransactionKind::Deposit,
                'booked_on' => '2026-11-10', 'booked_amount' => '10.00', 'currency' => 'EUR', 'match_status' => $status,
            ]);
        }
        $admin = $this->orgAdmin();
        $worker = $this->userWithRole(UserRole::User->value);

        $items = array_values(array_filter($this->queue($admin), static fn (WorkItem $i): bool => $i->source === 'firefly'));

        $counts = array_combine(array_map(static fn (WorkItem $i): string => (string) $i->url, $items), array_map(static fn (WorkItem $i): int => $i->count, $items));
        ksort($counts);
        $expected = [
            route('admin.firefly.index', ['match' => FireflyMatchStatus::Unmatched->value]) => 2,
            route('admin.firefly.index', ['match' => FireflyMatchStatus::Ambiguous->value]) => 1,
        ];
        ksort($expected);
        $this->assertSame($expected, $counts);
        $this->assertNull($items[0]->dueAt);
        $this->assertSame([], array_values(array_filter($this->queue($worker), static fn (WorkItem $i): bool => $i->source === 'firefly')));
    }

    public function test_firefly_source_is_silent_without_the_plugin(): void {
        FireflyTransaction::query()->forceCreate([
            'organization_id' => $this->organization->id, 'remote_journal_id' => '1', 'kind' => FireflyTransactionKind::Deposit,
            'booked_on' => '2026-11-10', 'booked_amount' => '10.00', 'currency' => 'EUR', 'match_status' => FireflyMatchStatus::Unmatched,
        ]);

        $this->assertSame([], array_values(array_filter($this->queue($this->orgAdmin()), static fn (WorkItem $i): bool => $i->source === 'firefly')));
    }

    /** @return list<WorkItem> */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        return app(WorkQueue::class)->itemsFor($viewer, $this->organization);
    }
}
