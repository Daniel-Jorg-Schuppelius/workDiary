<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskBoardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\User\Permission;
use App\Models\{AuditLog, Organization, ServiceQueue, ServiceTicket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-160: Queue-Board — org-gescopte Spalten in der Reihenfolge
 * der Zustandsmaschine, Filter, Massenzuweisung/Queue-Wechsel mit Gate JE
 * Ticket (Übersprungene werden gezählt), Requeue-Audit als Datenbasis für
 * MVP-159, 403 ohne serviceTicket.view.
 */
final class HelpdeskBoardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            ...$overrides,
        ]);
    }

    public function test_board_shows_org_scoped_columns_in_status_machine_order(): void {
        // In Arbeit VOR Gemeldet angelegt — die Spaltenreihenfolge (Zustands-
        // maschine) muss dennoch Gemeldet zuerst zeigen, nicht das Datum.
        $this->ticket(['title' => 'Board-Alpha', 'status' => ServiceTicketStatus::InProgress, 'reported_at' => now()->subDay()]);
        $this->ticket(['title' => 'Board-Beta', 'status' => ServiceTicketStatus::Reported]);

        ServiceTicket::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'title' => 'Fremdes-Geheimticket',
        ]);

        $response = $this->actingAs($this->agent)->get(route('helpdesk.board.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Board-Beta', 'Board-Alpha']);
        $response->assertDontSee('Fremdes-Geheimticket');
    }

    public function test_board_filters_by_queue_sqid_and_search(): void {
        $queueA = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha-Queue']);
        $queueB = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Beta-Queue']);
        $this->ticket(['title' => 'Drucker brennt', 'queue_id' => $queueA->id]);
        $this->ticket(['title' => 'Monitor flackert', 'queue_id' => $queueB->id]);

        $this->actingAs($this->agent)
            ->get(route('helpdesk.board.index', ['queue' => $queueA->sqid]))
            ->assertOk()
            ->assertSee('Drucker brennt')
            ->assertDontSee('Monitor flackert');

        $this->actingAs($this->agent)
            ->get(route('helpdesk.board.index', ['q' => 'flackert']))
            ->assertOk()
            ->assertSee('Monitor flackert')
            ->assertDontSee('Drucker brennt');
    }

    public function test_board_requires_service_ticket_view(): void {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($member)->get(route('helpdesk.board.index'))->assertForbidden();
        $this->actingAs($member)
            ->post(route('helpdesk.board.bulk-assign'), ['ids' => ['x'], 'assignee' => 'y'])
            ->assertForbidden();
    }

    public function test_bulk_assign_skips_foreign_and_unauthorized_tickets(): void {
        $mine = $this->ticket(['title' => 'Meins']);
        $other = $this->ticket(['title' => 'Auch meins']);
        $foreign = ServiceTicket::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $assignee = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.board.bulk-assign'), [
                'ids' => [$mine->sqid, $other->sqid, $foreign->sqid],
                'assignee' => $assignee->sqid,
            ])
            ->assertRedirect(route('helpdesk.board.index'));

        $this->assertSame((int) $assignee->id, (int) $mine->fresh()->assigned_to_user_id);
        $this->assertSame((int) $assignee->id, (int) $other->fresh()->assigned_to_user_id);
        // Fremde Org bleibt unangetastet (als übersprungen gezählt);
        // refresh() umgeht den Org-Scope, fresh() würde null liefern.
        $this->assertNull($foreign->refresh()->assigned_to_user_id);

        // Nur-Lese-Recht: viewAny passiert, aber das assign-Gate je Ticket
        // überspringt — keine Zuweisung.
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::ServiceTicketView->value);
        $third = $this->ticket(['title' => 'Drittes']);

        $this->actingAs($viewer)
            ->post(route('helpdesk.board.bulk-assign'), [
                'ids' => [$third->sqid],
                'assignee' => $assignee->sqid,
            ])
            ->assertRedirect(route('helpdesk.board.index'));

        $this->assertNull($third->fresh()->assigned_to_user_id);
    }

    public function test_bulk_assign_rejects_foreign_assignee(): void {
        $mine = $this->ticket();
        $foreignUser = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.board.bulk-assign'), [
                'ids' => [$mine->sqid],
                'assignee' => $foreignUser->sqid,
            ])
            ->assertSessionHasErrors('assignee');

        $this->assertNull($mine->fresh()->assigned_to_user_id);
    }

    public function test_bulk_move_changes_queue_and_audits_requeued(): void {
        $source = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Eingang']);
        $target = ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Zweite Ebene']);
        $ticket = $this->ticket(['queue_id' => $source->id]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.board.bulk-queue'), [
                'ids' => [$ticket->sqid],
                'queue' => $target->sqid,
            ])
            ->assertRedirect(route('helpdesk.board.index'));

        $this->assertSame((int) $target->id, (int) $ticket->fresh()->queue_id);
        // Requeue-Audit (Datenbasis für die Weiterleitungs-Kennzahl in MVP-159).
        $this->assertSame(1, AuditLog::query()->where('event', 'service_ticket.requeued')->count());

        // Idempotent: unveränderte Queue erzeugt KEIN zweites Audit-Rauschen.
        $this->actingAs($this->agent)
            ->post(route('helpdesk.board.bulk-queue'), [
                'ids' => [$ticket->fresh()->sqid],
                'queue' => $target->sqid,
            ])
            ->assertRedirect(route('helpdesk.board.index'));
        $this->assertSame(1, AuditLog::query()->where('event', 'service_ticket.requeued')->count());
    }

    public function test_bulk_move_rejects_foreign_queue(): void {
        $ticket = $this->ticket();
        $foreignQueue = ServiceQueue::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'name' => 'Fremde Queue',
        ]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.board.bulk-queue'), [
                'ids' => [$ticket->sqid],
                'queue' => $foreignQueue->sqid,
            ])
            ->assertSessionHasErrors('queue');

        $this->assertNull($ticket->fresh()->queue_id);
        $this->assertSame(0, AuditLog::query()->where('event', 'service_ticket.requeued')->count());
    }
}
