<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DelegationConnectionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Procedure\ProcedureRunStatus;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\Task\TaskStatus;
use App\Enums\User\{Permission, UserRole};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Audit\AuditLog;
use App\Models\Delegation\WorkDelegation;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Procedure\ProcedureRun;
use App\Models\Project\{Project, Task};
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Time\TimeEntry;
use App\Notifications\GenericEventNotification;
use App\Services\Delegation\WorkDelegationService;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\{MorphMap, Sqid};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, Notification};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Anschlüsse der Vertretung und endgültige Übergabe (Feature 202, MVP-1648):
 * Policies und „Meine“-Sichten folgen der Zuweisung auch für die Vertretung,
 * Rechte bleiben bei der handelnden Person; Übergabe je Bereich; Benachrichtigung
 * beim Anlegen; Ende mit der Abwesenheit. Akzeptanzkriterien 1–4.
 */
final class DelegationConnectionsTest extends TestCase {
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

    private function delegate(WorkDelegationKind $kind = WorkDelegationKind::Vacation, Vacation|SickLeave|null $absence = null, ?User $actor = null): WorkDelegation {
        return app(WorkDelegationService::class)->create(
            $this->anna,
            $this->ben,
            CarbonImmutable::parse('2026-11-12'),
            CarbonImmutable::parse('2026-11-20'),
            $kind,
            $absence,
            null,
            $actor ?? $this->anna,
        );
    }

    private function order(User $assignee, string $title, ?User $owner = null): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => ($owner ?? $assignee)->id,
            'assigned_user_id' => $assignee->id,
            'title' => $title,
            'content' => $title,
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);
    }

    /** @return list<string> */
    private function queueTitles(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return array_map(static fn (WorkItem $i): string => $i->title, $queue->itemsFor($viewer, $this->organization));
    }

    /** Akzeptanzkriterium 1: sehen und bearbeiten während der Vertretung, danach nicht mehr. */
    public function test_the_deputy_sees_and_works_on_the_orders_of_the_absent_person_only_during_the_delegation(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $entry = $this->order($this->anna, 'Jahresabschluss Muster GmbH', owner: $lead);
        $this->assertFalse(Gate::forUser($this->ben)->allows('view', $entry));
        $this->assertFalse(Gate::forUser($this->ben)->allows('start', $entry));

        $delegation = $this->delegate();
        $this->assertContains('Jahresabschluss Muster GmbH', $this->queueTitles($this->ben));
        $this->assertTrue(Gate::forUser($this->ben)->allows('view', $entry));
        $this->assertTrue(Gate::forUser($this->ben)->allows('start', $entry));
        $this->assertTrue(Gate::forUser($this->ben)->allows('complete', $entry));
        $this->actingAs($this->ben)->get(route('diary.show', $entry))->assertOk();
        // „Nur meine“ zeigt den Auftrag der vertretenen Person mit; Massenabzüge folgen der Policy.
        $this->actingAs($this->ben)->get(route('diary.index', ['mine' => 1]))->assertOk()->assertSee('Jahresabschluss Muster GmbH');
        $this->assertContains($entry->id, DiaryEntry::query()->visibleInBulkTo($this->ben)->pluck('id')->all());

        app(WorkDelegationService::class)->end($delegation, $this->anna);
        $this->assertNotContains('Jahresabschluss Muster GmbH', $this->queueTitles($this->ben));
        $this->assertFalse(Gate::forUser($this->ben)->allows('view', $entry->fresh()));
        $this->assertFalse(Gate::forUser($this->ben)->allows('start', $entry->fresh()));
        $this->actingAs($this->ben)->get(route('diary.show', $entry))->assertForbidden();
        $this->assertNotContains($entry->id, DiaryEntry::query()->visibleInBulkTo($this->ben)->pluck('id')->all());
    }

    /** Akzeptanzkriterium 2: die Vertretung verleiht kein Prüfrecht. */
    public function test_a_deputy_without_the_review_right_cannot_approve_an_order_of_the_represented_person(): void {
        $entry = $this->order($this->anna, 'Umsatzsteuer Oktober');
        $entry->forceFill(['review_submitter_user_id' => $this->anna->id])->save();
        $this->delegate();

        $this->assertTrue(Gate::forUser($this->ben)->allows('submitReview', $entry), 'Arbeit aus der Zuweisung geht');
        $this->assertFalse($this->ben->can(Permission::OrderReview->value));
        $this->assertFalse(Gate::forUser($this->ben)->allows('approveReview', $entry));
        $this->assertFalse(Gate::forUser($this->ben)->allows('markInvoiced', $entry), 'Rechte prüft die handelnde Person');

        // Mit eigenem Prüfrecht darf Ben prüfen — das Recht macht es, nicht die Vertretung.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->ben->givePermissionTo(Permission::OrderReview->value);
        $this->ben->unsetRelation('permissions');
        $this->assertTrue(Gate::forUser($this->ben)->allows('approveReview', $entry));
    }

    /** Akzeptanzkriterium 3: Zeiten und Abwesenheiten der vertretenen Person bleiben unsichtbar. */
    public function test_times_and_absences_of_the_represented_person_stay_invisible_to_the_deputy(): void {
        $time = TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->anna->id]);
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        $sickLeave = SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->anna->id]);
        $this->delegate(WorkDelegationKind::Sickness);

        $gate = Gate::forUser($this->ben);
        $this->assertFalse($gate->allows('view', $time));
        $this->assertFalse($gate->allows('view', $vacation));
        $this->assertFalse($gate->allows('view', $sickLeave));
        $this->assertNotContains($time->id, TimeEntry::query()->visibleTo($this->ben)->pluck('id')->all());
        $this->assertFalse($gate->allows('viewReason', WorkDelegation::query()->sole()));
        foreach ($this->queueTitles($this->ben) as $title) {
            $this->assertStringNotContainsString('Urlaub', $title);
        }
    }

    /** Akzeptanzkriterium 4: zwei Nachfolgen, nur die gewählten Bereiche, Anzahl im Protokoll. */
    public function test_the_final_handover_moves_exactly_the_chosen_areas_to_two_successors_and_logs_the_counts(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $carl = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carl Probe']);
        $dora = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Dora Test']);
        $first = $this->order($this->anna, 'Auftrag eins');
        $second = $this->order($this->anna, 'Auftrag zwei');
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'assigned_to' => $this->anna->id, 'title' => 'Aufgabe A', 'status' => TaskStatus::Open]);
        $task->assignees()->attach([$this->anna->id, $this->ben->id]);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'assigned_to_user_id' => $this->anna->id, 'status' => ServiceTicketStatus::Reported]);

        $this->actingAs($lead)->get(route('delegations.handover.dialog', $this->anna))
            ->assertOk()
            ->assertSee('handovers[assigned_orders]', false)
            ->assertSee('handovers[assigned_tasks]', false)
            ->assertSee('handovers[assigned_service_tickets]', false);

        $this->actingAs($lead)->post(route('delegations.handover.store', $this->anna), [
            'handovers' => [
                'assigned_orders' => Sqid::encode(User::class, (int) $carl->id),
                'assigned_tasks' => Sqid::encode(User::class, (int) $dora->id),
                'assigned_service_tickets' => '',
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame($carl->id, $first->fresh()?->assigned_user_id);
        $this->assertSame($carl->id, $second->fresh()?->assigned_user_id);
        $task->refresh();
        $this->assertSame($dora->id, $task->assigned_to);
        $this->assertEqualsCanonicalizing([$dora->id, $this->ben->id], $task->assignees()->pluck('users.id')->all(), 'die übrigen Bearbeiter bleiben');
        $this->assertSame($this->anna->id, $ticket->fresh()?->assigned_to_user_id, 'nicht gewählter Bereich bleibt');
        $this->assertFalse($this->anna->fresh()?->isDeactivated(), 'ohne Austritt');

        $audit = AuditLog::query()->where('event', 'user.workHandedOver')->sole();
        $encoded = json_encode($audit->changes, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"assigned_orders"', $encoded);
        $this->assertStringContainsString('"count":2', $encoded);
        $this->assertStringContainsString('"assigned_tasks"', $encoded);
        $this->assertStringNotContainsString('assigned_service_tickets', $encoded);
    }

    public function test_the_handover_needs_the_management_right_and_an_active_member_of_the_own_organization(): void {
        $this->actingAs($this->ben)->get(route('delegations.handover.dialog', $this->anna))->assertForbidden();
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->get(route('delegations.handover.dialog', $lead))->assertForbidden();
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($lead)->get(route('delegations.handover.dialog', $foreign))->assertNotFound();
        $this->actingAs($lead)->post(route('delegations.handover.store', $this->anna), ['handovers' => []])->assertSessionHasErrors('handovers');
    }

    public function test_change_responsibility_in_the_order_list_moves_only_editable_orders(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $editable = $this->order($this->anna, 'Bearbeitbar', owner: $lead);
        $foreign = $this->order($this->anna, 'Fremd', owner: $this->anna);

        $this->actingAs($lead)->get(route('diary.index'))->assertOk()
            ->assertSee(route('diary.bulk-assign'), false)
            ->assertSee('value="' . $editable->sqid . '"', false);

        $response = $this->actingAs($this->ben)->post(route('diary.bulk-assign'), [
            'ids' => [$foreign->sqid],
            'assignee' => Sqid::encode(User::class, (int) $this->ben->id),
        ]);
        $response->assertRedirect();
        $this->assertSame($this->anna->id, $foreign->fresh()?->assigned_user_id, 'ohne Bearbeitungsrecht übersprungen');

        $this->actingAs($lead)->post(route('diary.bulk-assign'), [
            'ids' => [$editable->sqid],
            'assignee' => Sqid::encode(User::class, (int) $this->ben->id),
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame($this->ben->id, $editable->fresh()?->assigned_user_id);
    }

    public function test_tickets_tasks_and_procedure_runs_follow_the_assignment_for_the_deputy(): void {
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_to_user_id' => $this->anna->id,
            'confidentiality' => ServiceTicket::CONFIDENTIALITY_RESTRICTED,
            'status' => ServiceTicketStatus::Reported,
        ]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'created_by' => $this->anna->id, 'assigned_to' => $this->anna->id, 'status' => TaskStatus::Open]);
        $task->assignees()->attach($this->anna->id);
        $run = ProcedureRun::factory()->create(['organization_id' => $this->organization->id, 'assigned_user_id' => $this->anna->id, 'status' => ProcedureRunStatus::InProgress]);

        // Leserecht für Tickets hat Ben selbst — die Vertretung ergänzt nur die Zuweisung.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->ben->givePermissionTo(Permission::ServiceTicketView->value);
        $this->ben->unsetRelation('permissions');
        $gate = Gate::forUser($this->ben);
        $this->assertFalse($gate->allows('view', $ticket));
        $this->assertFalse(ServiceTicket::query()->visibleTo($this->ben)->whereKey($ticket->id)->exists());
        $this->assertFalse($gate->allows('update', $task));

        $this->delegate();
        $gate = Gate::forUser($this->ben);
        $this->assertTrue($gate->allows('view', $ticket), 'vertraulich: die Zuweisung an die vertretene Person zählt');
        $this->assertTrue(ServiceTicket::query()->visibleTo($this->ben)->whereKey($ticket->id)->exists());
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('view', $ticket), 'ohne Leserecht nichts');
        $this->assertSame($this->ben->can(Permission::TaskManage->value), $gate->allows('update', $task));
        if ($this->ben->can(Permission::ProcedureRunView->value)) {
            $this->assertContains((string) $run->templateVersion?->template?->name ?: __('work-queue.item.procedure_run'), $this->queueTitles($this->ben));
        }
        $this->assertSame(1, app(\App\Services\Procedure\WorkQueue\ProcedureRunWorkItemSource::class)->count($this->anna));
    }

    public function test_the_deputy_is_notified_on_creation_without_the_reason(): void {
        Notification::fake();
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->delegate(WorkDelegationKind::Sickness, actor: $lead);

        Notification::assertSentTo($this->ben, GenericEventNotification::class, static function (GenericEventNotification $n): bool {
            $text = json_encode($n->payload, JSON_THROW_ON_ERROR);

            return $n->event === NotificationEvent::DelegationCreated
                && str_contains($text, 'Anna Beispiel')
                && ! str_contains(mb_strtolower($text), 'krank')
                && ! str_contains(mb_strtolower($text), 'sick');
        });
        Notification::assertNotSentTo($this->anna, GenericEventNotification::class);
    }

    public function test_a_cancelled_vacation_ends_its_delegation_and_a_cancelled_sick_leave_too(): void {
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-20',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        $delegation = $this->delegate(absence: $vacation);
        $this->assertSame('vacations', MorphMap::alias(Vacation::class));

        $vacation->update(['status' => VacationStatus::Cancelled]);
        $this->assertTrue($delegation->fresh()?->isEnded());
        $this->assertNotContains($this->anna->id, array_map(static fn (User $u): int => (int) $u->id, app(\App\Services\Delegation\ActsForResolver::class)->for($this->ben)));

        $sickLeave = SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->anna->id]);
        $fromSickness = $this->delegate(WorkDelegationKind::Sickness, $sickLeave);
        $other = WorkDelegation::query()->whereKeyNot($fromSickness->id)->sole();
        $sickLeave->update(['cancelled_at' => now()]);
        $this->assertTrue($fromSickness->fresh()?->isEnded());
        $this->assertTrue($other->fresh()?->isEnded(), 'bereits beendet bleibt beendet');
    }
}
