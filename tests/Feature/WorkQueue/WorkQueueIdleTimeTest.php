<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueIdleTimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\WorkQueue;

use App\Enums\Diary\Status;
use App\Enums\User\UserRole;
use App\Models\Classification\EntryType;
use App\Models\Communication\Comment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Models\Time\TimeEntry;
use App\Services\Reporting\{EntryTypeLeadTimeBuilder, ManagementOverviewService};
use App\Services\Reporting\Management\LeadTimeKpiSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\Management\DormantWorkKpiSource;
use App\Services\WorkQueue\WorkQueue;
use App\Support\Sqid;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Liegezeit am Vorgang und Durchlaufzeit je Vorgangsart (Feature 179,
 * MVP-1670): „zuletzt bewegt“ der Quellen, Spalte, Sortierung und Filter im
 * Arbeitsvorrat, Schwelle als Organisationseinstellung, Kennzahlen der
 * Leitungssicht — gezählt am Vorgang, nie je Person.
 */
final class WorkQueueIdleTimeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
    }

    /** @param  array<string, mixed>  $attributes */
    private function worker(array $attributes = []): User {
        return $this->userWithRole(UserRole::User->value, attributes: $attributes);
    }

    /** @return list<WorkItem> */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        app(WorkQueue::class)->forget($viewer, $this->organization);

        return app(WorkQueue::class)->itemsFor($viewer, $this->organization);
    }

    private function item(User $viewer, string $key): WorkItem {
        foreach ($this->queue($viewer) as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }
        $this->fail("Vorgang {$key} fehlt im Arbeitsvorrat.");
    }

    private function taskMovedAt(User $user, string $title, string $movedAt): Task {
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $user->id, 'title' => $title, 'due_date' => null]);
        Task::query()->whereKey($task->id)->toBase()->update(['updated_at' => $movedAt]);

        return $task;
    }

    public function test_work_item_keeps_moved_at_in_its_cache_form_and_counts_idle_days(): void {
        $item = new WorkItem('task', 'tasks:1', 'Aufgabe', null, movedAt: CarbonImmutable::parse('2026-10-28 08:00:00'));
        $copy = WorkItem::fromArray($item->toArray());
        $now = CarbonImmutable::now();

        $this->assertSame($item->movedAt?->getTimestamp(), $copy->movedAt?->getTimestamp());
        $this->assertSame(15, $copy->idleDays($now));
        $this->assertTrue($copy->isDormant($now, 14));
        $this->assertFalse($copy->isDormant($now, 16));
        $this->assertSame('Vertreten', $copy->delegatedFor('Vertreten')->onBehalfOf);
        $this->assertSame($copy->movedAt?->getTimestamp(), $copy->delegatedFor('X')->movedAt?->getTimestamp(), 'Die Vertretung behält „zuletzt bewegt“.');
        $this->assertNull((new WorkItem('x', 'x:1', 'Ohne', null))->idleDays($now), 'Ohne „zuletzt bewegt“ keine Liegezeit.');
        $this->assertSame('2026-11-02 09:00:00', WorkItem::lastMoved('2026-10-01 09:00:00', null, CarbonImmutable::parse('2026-11-02 09:00:00'), '')?->format('Y-m-d H:i:s'));
    }

    public function test_an_assigned_order_moves_with_its_latest_time_booking_or_comment(): void {
        $user = $this->worker(['name' => 'Olga Ordner']);
        Carbon::setTestNow('2026-10-01 09:00:00');
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'assigned_user_id' => $user->id, 'title' => 'Jahresabschluss Muster GmbH', 'due_date' => null, 'planned_at' => null]);
        Carbon::setTestNow('2026-11-02 09:00:00');
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'diary_entry_id' => $entry->id, 'date' => '2026-11-02', 'minutes' => 30]);
        Carbon::setTestNow('2026-11-12 10:00:00');
        DiaryEntry::query()->whereKey($entry->id)->toBase()->update(['updated_at' => '2026-10-01 09:00:00']);

        $key = $entry->getMorphClass() . ':' . $entry->id;
        $this->assertSame(10, $this->item($user, $key)->idleDays(CarbonImmutable::now()), 'Die Zeitbuchung bewegt den Auftrag.');

        Carbon::setTestNow('2026-11-10 15:00:00');
        Comment::query()->create(['organization_id' => $this->organization->id, 'commentable_type' => $entry->getMorphClass(), 'commentable_id' => $entry->id, 'user_id' => $user->id, 'body' => 'Unterlagen angefordert.']);
        Carbon::setTestNow('2026-11-12 10:00:00');
        DiaryEntry::query()->whereKey($entry->id)->toBase()->update(['updated_at' => '2026-10-01 09:00:00']);

        $this->assertSame(2, $this->item($user, $key)->idleDays(CarbonImmutable::now()), 'Der Kommentar bewegt den Auftrag.');
    }

    public function test_the_queue_shows_idle_days_marks_dormant_items_and_filters_and_sorts_by_them(): void {
        $user = $this->worker();
        $this->taskMovedAt($user, 'Liegengebliebene Aufgabe', '2026-10-23 08:00:00');
        $this->taskMovedAt($user, 'Frische Aufgabe', '2026-11-09 08:00:00');

        $this->actingAs($user)->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee(__('work-queue.column.idle'))
            ->assertSee(trans_choice('work-queue.idle.days', 20, ['count' => 20]))
            ->assertSee(trans_choice('work-queue.idle.days', 3, ['count' => 3]))
            ->assertSee(__('work-queue.idle.dormant'));

        $this->actingAs($user)->get(route('work-queue.index', ['dormant' => 1]))
            ->assertOk()
            ->assertSee('Liegengebliebene Aufgabe')
            ->assertDontSee('Frische Aufgabe');

        $this->actingAs($user)->get(route('work-queue.index', ['sort' => 'idle', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Liegengebliebene Aufgabe', 'Frische Aufgabe']);
        $this->actingAs($user)->get(route('work-queue.index', ['sort' => 'idle', 'dir' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Frische Aufgabe', 'Liegengebliebene Aufgabe']);
    }

    public function test_the_dormant_threshold_is_an_organization_setting_for_organization_editors(): void {
        $admin = $this->orgAdmin();
        $user = $this->worker();
        $queue = app(WorkQueue::class);
        $this->assertSame(WorkQueue::DEFAULT_DORMANT_DAYS, $queue->dormantDays($this->organization));

        $this->actingAs($user)->get(route('work-queue.settings.edit'))->assertForbidden();
        $this->actingAs($user)->post(route('work-queue.settings.update'), ['dormant_days' => 30])->assertForbidden();
        $this->actingAs($admin)->get(route('work-queue.settings.edit', ['dialog' => 1]))->assertOk()->assertSee('name="dormant_days"', false);
        $this->actingAs($admin)->post(route('work-queue.settings.update'), ['dormant_days' => 0])->assertSessionHasErrors('dormant_days');
        $this->actingAs($admin)->post(route('work-queue.settings.update'), ['dormant_days' => 30])->assertSessionHasNoErrors();

        $this->organization->refresh();
        $this->assertSame(30, $queue->dormantDays($this->organization));

        $this->taskMovedAt($user, 'Zwanzig Tage alt', '2026-10-23 08:00:00');
        // Frische Instanzen: die Organisation des Nutzers ist aus den Anfragen oben noch geladen.
        app()->instance('currentOrganization', $this->organization);
        $user->unsetRelations();
        $this->actingAs($user)->get(route('work-queue.index', ['dormant' => 1]))
            ->assertOk()
            ->assertDontSee('Zwanzig Tage alt');
    }

    public function test_the_management_view_counts_dormant_items_of_the_viewers_queue(): void {
        $admin = $this->orgAdmin();
        $this->taskMovedAt($admin, 'Alte Aufgabe', '2026-10-01 08:00:00');
        $this->taskMovedAt($admin, 'Neue Aufgabe', '2026-11-11 08:00:00');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $now = CarbonImmutable::now();

        $kpis = app(DormantWorkKpiSource::class)->kpis($admin, $this->organization, $now->startOfMonth(), $now);

        $this->assertCount(1, $kpis);
        $this->assertSame(__('work-queue.kpi.dormant'), $kpis[0]->label);
        $this->assertSame('1', $kpis[0]->value);
        $this->assertSame(route('work-queue.index', ['dormant' => 1, 'sort' => 'idle', 'dir' => 'desc']), $kpis[0]->url);
        $labels = array_map(static fn ($kpi): string => $kpi->label, app(ManagementOverviewService::class)->build($admin, $this->organization, $now->startOfMonth(), $now)['operations'] ?? []);
        $this->assertContains(__('work-queue.kpi.dormant'), $labels);
    }

    public function test_lead_time_per_order_type_is_the_median_of_orders_completed_in_twelve_months(): void {
        $admin = $this->orgAdmin();
        $owner = $this->worker();
        $annual = EntryType::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Jahresabschluss']);
        $payroll = EntryType::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Lohn']);
        $order = function (EntryType $type, string $created, ?string $completed, Status $status = Status::Done) use ($owner): void {
            $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $owner->id, 'entry_type_id' => $type->id, 'status' => $status->value]);
            DiaryEntry::query()->whereKey($entry->id)->toBase()->update(['created_at' => $created, 'completed_at' => $completed]);
        };
        $order($annual, '2026-09-01 08:00:00', '2026-09-03 08:00:00');
        $order($annual, '2026-09-01 08:00:00', '2026-09-05 08:00:00');
        $order($annual, '2026-08-01 08:00:00', '2026-08-11 08:00:00');
        $order($annual, '2026-10-01 08:00:00', '2026-10-30 08:00:00', Status::Cancelled);
        $order($annual, '2025-08-01 08:00:00', '2025-09-01 08:00:00');
        $order($payroll, '2026-11-02 08:00:00', '2026-11-03 08:00:00');

        $rows = app(EntryTypeLeadTimeBuilder::class)->build((int) $this->organization->id, CarbonImmutable::parse('2025-11-13'), CarbonImmutable::parse('2026-11-12'));
        $this->assertSame([
            ['entryTypeId' => (int) $annual->id, 'entryTypeName' => 'Jahresabschluss', 'completed' => 3, 'medianDays' => 4.0],
            ['entryTypeId' => (int) $payroll->id, 'entryTypeName' => 'Lohn', 'completed' => 1, 'medianDays' => 1.0],
        ], $rows, 'Storniert und älter als zwölf Monate zählen nicht.');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $now = CarbonImmutable::now();
        $kpis = app(LeadTimeKpiSource::class)->kpis($admin, $this->organization, $now->startOfMonth(), $now);
        $this->assertSame(__('reporting.management.kpi.lead_time', ['type' => 'Jahresabschluss']), $kpis[0]->label);
        $this->assertSame(trans_choice('reporting.management.kpi.lead_time_days', 2, ['days' => '4']), $kpis[0]->value);
        $this->assertSame(trans_choice('reporting.management.kpi.lead_time_days', 1, ['days' => '1']), $kpis[1]->value);
        $this->assertStringContainsString('entry_type_id=' . Sqid::encode(EntryType::class, (int) $annual->id), (string) $kpis[0]->url);
        $this->assertSame([], app(LeadTimeKpiSource::class)->kpis($owner, $this->organization, $now->startOfMonth(), $now), 'Ohne Recht der Auftragstypanalyse keine Kennzahl.');

        $this->actingAs($admin)->get(route('reports.entry-types', ['from' => '2025-11-13', 'to' => '2026-11-12']))
            ->assertOk()
            ->assertSee(__('reporting.lead_time.title'))
            ->assertSeeInOrder(['Jahresabschluss', '3', '4,0']);
    }
}
