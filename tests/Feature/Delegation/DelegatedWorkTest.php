<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DelegatedWorkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\UserRole;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Delegation\WorkDelegation;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Notifications\GenericEventNotification;
use App\Services\Delegation\{ActsForResolver, WorkDelegationService};
use App\Services\Diary\WorkQueue\AssignedEntryWorkItemSource;
use App\Services\Notification\NotificationDispatcher;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\NotificationText;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Was eine Vertretung bewirkt (Feature 202, MVP-1647): Arbeitsvorrat mit den
 * zugewiesenen Vorgängen der vertretenen Person, Benachrichtigungen zugewiesener
 * Arbeit — und was sie nicht bewirkt (Persönliches, Rechte, nach dem Ende).
 */
final class DelegatedWorkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $anna;

    private User $ben;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
        $this->anna = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Anna Beispiel']);
        $this->ben = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Ben Muster']);
    }

    private function delegate(?string $until = '2026-11-20'): WorkDelegation {
        return app(WorkDelegationService::class)->create(
            $this->anna,
            $this->ben,
            CarbonImmutable::parse('2026-11-12'),
            $until !== null ? CarbonImmutable::parse($until) : null,
            WorkDelegationKind::Vacation,
            null,
            null,
            $this->anna,
        );
    }

    /** @return list<WorkItem> */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return $queue->itemsFor($viewer, $this->organization);
    }

    private function order(User $assignee, string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $assignee->id,
            'assigned_user_id' => $assignee->id,
            'title' => $title,
            'due_date' => null,
            'planned_at' => null,
        ]);
    }

    public function test_the_deputy_sees_the_assigned_work_of_the_represented_person_marked_and_without_duplicates(): void {
        $this->order($this->anna, 'Jahresabschluss Muster GmbH');
        $shared = Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $this->anna->id, 'title' => 'Gemeinsame Aufgabe']);
        $shared->assignees()->attach($this->ben->id);
        $this->order($this->ben, 'Bens eigener Auftrag');
        // Persönliches der vertretenen Person ist keine delegierbare Arbeit.
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);

        $before = array_map(static fn (WorkItem $i): string => $i->title, $this->queue($this->ben));
        $this->assertNotContains('Jahresabschluss Muster GmbH', $before);

        $this->delegate();
        $items = $this->queue($this->ben);
        $byTitle = [];
        foreach ($items as $item) {
            $byTitle[$item->title][] = $item;
        }

        $this->assertArrayHasKey('Jahresabschluss Muster GmbH', $byTitle);
        $this->assertSame('Anna Beispiel', $byTitle['Jahresabschluss Muster GmbH'][0]->onBehalfOf);
        $this->assertCount(1, $byTitle['Gemeinsame Aufgabe'], 'derselbe Gegenstand nur einmal');
        $this->assertNull($byTitle['Gemeinsame Aufgabe'][0]->onBehalfOf, 'die eigene Zeile gewinnt');
        $this->assertNull($byTitle['Bens eigener Auftrag'][0]->onBehalfOf);
        foreach ($items as $item) {
            $this->assertNotSame('vacation_approval', $item->source, 'keine Freigaben über die Vertretung');
        }

        // Die vertretene Person sieht nichts von Ben.
        $this->assertNotContains('Bens eigener Auftrag', array_map(static fn (WorkItem $i): string => $i->title, $this->queue($this->anna)));
    }

    public function test_after_the_end_the_deputy_no_longer_sees_the_work(): void {
        $this->order($this->anna, 'Lohnabrechnung November');
        $delegation = $this->delegate();
        $this->assertContains('Lohnabrechnung November', array_map(static fn (WorkItem $i): string => $i->title, $this->queue($this->ben)));

        app(WorkDelegationService::class)->end($delegation, $this->anna);
        $this->assertNotContains('Lohnabrechnung November', array_map(static fn (WorkItem $i): string => $i->title, $this->queue($this->ben)));

        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->assertNotContains('Lohnabrechnung November', array_map(static fn (WorkItem $i): string => $i->title, $this->queue($this->ben)));
    }

    public function test_the_cache_key_changes_when_a_delegation_starts(): void {
        $this->order($this->anna, 'Umsatzsteuer Oktober');
        $queue = app(WorkQueue::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->assertNotContains('Umsatzsteuer Oktober', array_map(static fn (WorkItem $i): string => $i->title, $queue->itemsFor($this->ben, $this->organization)));

        $this->delegate();
        // Ohne forget(): die neue Vertretung wirkt sofort, nicht erst nach Ablauf des Cache.
        $this->assertContains('Umsatzsteuer Oktober', array_map(static fn (WorkItem $i): string => $i->title, $queue->itemsFor($this->ben, $this->organization)));
    }

    public function test_delegable_work_counts_only_assigned_open_items(): void {
        $this->order($this->anna, 'Offen');
        $this->order($this->anna, 'Noch offen');
        $this->order($this->ben, 'Fremd');

        $this->assertSame(2, app(AssignedEntryWorkItemSource::class)->count($this->anna));
        $work = app(WorkDelegationService::class)->delegableWork($this->anna);
        $this->assertContains(['key' => 'assigned_entry', 'label' => __('work-queue.source.assigned_entry'), 'count' => 2], $work);
    }

    public function test_notifications_on_assigned_work_also_reach_the_deputy_but_nothing_personal(): void {
        $entry = $this->order($this->anna, 'Auftrag mit Kommentar');
        $this->delegate();
        Notification::fake();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->notify(NotificationEvent::DiaryCommentCreated, $entry, $this->anna, ['title' => 'Neuer Kommentar']);
        Notification::assertSentTo($this->anna, GenericEventNotification::class, static fn (GenericEventNotification $n): bool => ! isset($n->payload['on_behalf_of']));
        Notification::assertSentTo($this->ben, GenericEventNotification::class, static function (GenericEventNotification $n): bool {
            return $n->event === NotificationEvent::DiaryCommentCreated
                && ($n->payload['on_behalf_of'] ?? null) === 'Anna Beispiel'
                && ($n->toArray(new User)['on_behalf_of'] ?? null) === 'Anna Beispiel';
        });

        Notification::fake();
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        $dispatcher->notify(NotificationEvent::VacationDecided, $vacation, $this->anna, ['title' => 'Urlaub genehmigt']);
        Notification::assertSentTo($this->anna, GenericEventNotification::class);
        Notification::assertNotSentTo($this->ben, GenericEventNotification::class);
    }

    public function test_no_forwarding_after_the_end(): void {
        $entry = $this->order($this->anna, 'Auftrag');
        app(WorkDelegationService::class)->end($this->delegate(), $this->anna);
        Notification::fake();

        app(NotificationDispatcher::class)->notify(NotificationEvent::DiaryCommentCreated, $entry, $this->anna, ['title' => 'Kommentar']);
        Notification::assertNotSentTo($this->ben, GenericEventNotification::class);
    }

    public function test_the_on_behalf_hint_precedes_the_message_text(): void {
        app()->setLocale('de');
        $this->assertSame('In Vertretung für Anna Beispiel: Bitte prüfen', NotificationText::message(['message' => 'Bitte prüfen', 'on_behalf_of' => 'Anna Beispiel']));
        $this->assertSame('In Vertretung für Anna Beispiel:', NotificationText::message(['on_behalf_of' => 'Anna Beispiel']));
        $this->assertSame('Bitte prüfen', NotificationText::message(['message' => 'Bitte prüfen']));
    }

    public function test_only_assigned_work_events_forward_to_the_deputy(): void {
        foreach ([NotificationEvent::TicketAssigned, NotificationEvent::OpenIssueAssigned, NotificationEvent::DiaryCommentCreated, NotificationEvent::ContractDeadlineDue] as $event) {
            $this->assertTrue($event->forwardsToDeputy(), $event->value);
        }
        foreach ([NotificationEvent::VacationDecided, NotificationEvent::TimeCorrectionDecided, NotificationEvent::OvertimeDecided, NotificationEvent::MonthClosureDecided,
            NotificationEvent::HrFileAckRequested, NotificationEvent::HrFileSubmissionDecided, NotificationEvent::LearningGraded, NotificationEvent::QualificationExpiring,
            NotificationEvent::SecurityNewDevice, NotificationEvent::ChatMessage, NotificationEvent::AttendanceOpenReminder, NotificationEvent::ShiftExchangeDecided,
            NotificationEvent::VacationRequested, NotificationEvent::EmergencyAssigned] as $event) {
            $this->assertFalse($event->forwardsToDeputy(), $event->value);
        }
    }

    public function test_the_delegation_grants_no_rights(): void {
        $this->delegate();
        $resolver = app(ActsForResolver::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->assertSame([(int) $this->anna->id], $resolver->principalIds($this->ben));
        foreach (['vacation.approve', 'sick-leave.viewAny', 'user.payroll.manage', 'user.delegation.manage'] as $permission) {
            $this->assertSame($this->anna->can($permission), $this->ben->can($permission), $permission);
            $this->assertFalse($this->ben->can($permission), $permission);
        }
    }
}
