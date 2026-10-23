<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\WorkQueue;

use App\Dashboard\Widgets\WorkQueueWidget;
use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\UserRole;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Enums\WorkQueue\{WorkItemCategory, WorkItemUrgency};
use App\Models\Absence\Vacation;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Task;
use App\Models\Time\MonthClosure;
use App\Models\Travel\Expense;
use App\Modules\ModuleRegistry;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class WorkQueueTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 10:00:00');
        $this->setUpOrganization();
    }

    /** Mitarbeiter mit den Rechten der Rolle „user“ (Aufträge sehen usw.). @param array<string, mixed> $attributes */
    private function worker(array $attributes = []): User {
        return $this->userWithRole(UserRole::User->value, attributes: $attributes);
    }

    /**
     * Factories anderer Organisationen verstellen den Spatie-Team-Kontext; im
     * Request setzt ihn die Middleware, hier wird er vor jeder Abfrage gesetzt.
     *
     * @return list<WorkItem>
     */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        return app(WorkQueue::class)->itemsFor($viewer, $this->organization);
    }

    /** @return list<string> */
    private function titlesFor(User $viewer): array {
        return array_map(static fn (WorkItem $item): string => $item->title, $this->queue($viewer));
    }

    private function vacation(User $user, VacationStatus $status = VacationStatus::Pending, ?Organization $organization = null): Vacation {
        return Vacation::create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'user_id' => $user->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'type' => VacationType::Vacation->value,
            'status' => $status->value,
        ]);
    }

    public function test_approvers_see_pending_requests_of_others_but_not_their_own_or_other_organizations(): void {
        $approver = $this->orgAdmin(['name' => 'Anna Admin']);
        $employee = $this->worker(['name' => 'Bernd Mitarbeiter']);
        $this->vacation($employee);
        $this->vacation($employee, VacationStatus::Approved);
        $this->vacation($approver);

        $foreign = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $foreign->id, 'name' => 'Fremde Person']);
        $this->vacation($stranger, organization: $foreign);

        $vacations = array_values(array_filter(
            $this->queue($approver),
            static fn (WorkItem $i): bool => $i->source === 'vacation_approval',
        ));

        $this->assertSame([__('work-queue.item.vacation', ['name' => 'Bernd Mitarbeiter'])], array_map(static fn (WorkItem $i): string => $i->title, $vacations));
        $this->assertSame([], $this->titlesFor($employee), 'Ohne Freigaberecht keine Freigaben, eigene Anträge nie.');
    }

    public function test_expenses_and_month_closings_need_the_approver_role(): void {
        $approver = $this->orgAdmin();
        $employee = $this->worker(['name' => 'Carla Kollegin']);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'status' => 'pending', 'description' => 'Bahnfahrt']);
        MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 9,
            'status' => MonthClosureStatus::Submitted,
        ]);

        $sources = array_map(static fn (WorkItem $item): string => $item->source, $this->queue($approver));

        $this->assertContains('expense_approval', $sources);
        $this->assertContains('month_closure', $sources);
        $this->assertSame([], $this->titlesFor($employee));
    }

    public function test_assigned_items_are_personal_and_sorted_by_urgency(): void {
        $user = $this->worker();
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'assigned_user_id' => $user->id, 'title' => 'Auftrag ohne Frist', 'due_date' => null, 'planned_at' => null]);
        OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_id' => $entry->id,
            'assignee_user_id' => $user->id,
            'title' => 'Überfälliger Punkt',
            'due_at' => '2026-10-20 12:00:00',
        ]);
        Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $user->id, 'title' => 'Bald fällige Aufgabe', 'due_date' => '2026-10-25']);
        Task::factory()->done()->create(['organization_id' => $this->organization->id, 'assigned_to' => $user->id, 'title' => 'Erledigte Aufgabe']);

        $items = $this->queue($user);
        $now = CarbonImmutable::now();

        $this->assertSame(['Überfälliger Punkt', 'Bald fällige Aufgabe', 'Auftrag ohne Frist'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame([WorkItemUrgency::Overdue, WorkItemUrgency::DueSoon, WorkItemUrgency::Open], array_map(static fn (WorkItem $i): WorkItemUrgency => $i->urgency($now), $items));
        $this->assertTrue($items[0]->personal && $items[1]->personal && $items[2]->personal);
    }

    public function test_shared_inbox_is_bundled_per_source_for_billing_managers_only(): void {
        foreach (['toggl:1' => IntegrationInboxStatus::Open, 'toggl:2' => IntegrationInboxStatus::Open, 'toggl:3' => IntegrationInboxStatus::Dismissed] as $id => $status) {
            IntegrationInboxItem::query()->create([
                'organization_id' => $this->organization->id,
                'plugin_id' => 'toggl',
                'target_type' => 'customer',
                'external_type' => 'client',
                'external_id' => $id,
                'dedupe_key' => 'client:' . $id,
                'case_type' => 'unmatched',
                'status' => $status->value,
                'remote_snapshot' => [],
            ]);
        }

        $items = array_values(array_filter(
            $this->queue($this->orgAdmin()),
            static fn (WorkItem $i): bool => $i->source === 'integration_inbox',
        ));

        $this->assertCount(1, $items);
        $this->assertSame(2, $items[0]->count);
        $this->assertFalse($items[0]->personal);
        $this->assertSame([], $this->titlesFor($this->worker()));
    }

    public function test_the_same_subject_from_two_sources_appears_once_and_a_failing_source_is_skipped(): void {
        Exceptions::fake();
        $registry = app(ModuleRegistry::class);
        $registry->contribute(WorkItemSource::class, WorkQueueTestLaterSource::class);
        $registry->contribute(WorkItemSource::class, WorkQueueTestOverdueSource::class);
        $registry->contribute(WorkItemSource::class, WorkQueueTestBrokenSource::class);

        $items = array_values(array_filter(
            $this->queue($this->worker()),
            static fn (WorkItem $i): bool => $i->key === 'probe:1',
        ));

        $this->assertCount(1, $items);
        $this->assertSame('test_overdue', $items[0]->source, 'Der dringlichere Eintrag bleibt.');
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_page_filters_by_category_and_personal_assignment(): void {
        $approver = $this->orgAdmin();
        $this->vacation($this->worker(['name' => 'Dora Antrag']));
        Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $approver->id, 'title' => 'Meine Aufgabe']);

        $this->actingAs($approver)->get(route('work-queue.index'))
            ->assertOk()->assertSee('Dora Antrag')->assertSee('Meine Aufgabe');
        $this->actingAs($approver)->get(route('work-queue.index', ['category' => WorkItemCategory::Task->value]))
            ->assertOk()->assertDontSee('Dora Antrag')->assertSee('Meine Aufgabe');
        $this->actingAs($approver)->get(route('work-queue.index', ['mine' => 1]))
            ->assertOk()->assertDontSee('Dora Antrag')->assertSee('Meine Aufgabe');
        $this->actingAs($approver)->get(route('work-queue.index', ['sort' => 'title', 'dir' => 'desc', 'source' => 'unbekannt']))
            ->assertOk()->assertSee('Meine Aufgabe');
    }

    public function test_empty_queue_and_widget_render(): void {
        $user = $this->worker();
        $this->actingAs($user)->get(route('work-queue.index'))->assertOk()->assertSee(__('work-queue.empty.title'));

        Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $user->id, 'title' => 'Kachel-Aufgabe']);
        $this->actingAs($user);
        $html = (string) app(WorkQueueWidget::class)->render($user);

        $this->assertStringContainsString('Kachel-Aufgabe', $html);
        $this->assertStringContainsString(route('work-queue.index'), $html);
    }
}

final class WorkQueueTestLaterSource implements WorkItemSource {
    public function key(): string {
        return 'test_later';
    }

    public function label(): string {
        return 'Später';
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        yield new WorkItem(source: 'test_later', key: 'probe:1', title: 'Probe', url: null, dueAt: CarbonImmutable::parse('2026-12-01'));
    }
}

final class WorkQueueTestOverdueSource implements WorkItemSource {
    public function key(): string {
        return 'test_overdue';
    }

    public function label(): string {
        return 'Überfällig';
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        yield new WorkItem(source: 'test_overdue', key: 'probe:1', title: 'Probe', url: null, dueAt: CarbonImmutable::parse('2026-10-01'));
    }
}

final class WorkQueueTestBrokenSource implements WorkItemSource {
    public function key(): string {
        return 'test_broken';
    }

    public function label(): string {
        return 'Defekt';
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return true;
    }

    public function items(User $viewer, Organization $organization): iterable {
        throw new RuntimeException('Quelle nicht erreichbar');
    }
}
