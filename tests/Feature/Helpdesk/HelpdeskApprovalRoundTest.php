<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskApprovalRoundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceRequestStatus;
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\Sales\ServiceOffering;
use App\Models\ServiceTicket\{BusinessService, ServiceQueue, ServiceRequest};
use App\Services\Approval\ApprovalService;
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Freigaberunden im gemeinsamen Genehmigungsbaustein: die höchste Runde eines
 * Objekts gilt, jede Regel des Dienstes zählt je Runde, abgelöste Stufen
 * verschwinden aus dem Genehmigungs-Eingang und sind nicht mehr entscheidbar.
 */
final class HelpdeskApprovalRoundTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $requester;

    private User $approverA;

    private User $approverB;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->requester = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->approverA = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->approverB = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Default', 'is_default' => true]);
    }

    /** Zweistufige Kette: Schritt 1 persönlich (A), Schritt 2 Rolle. */
    private function twoStepRequest(string $name): ServiceRequest {
        $service = BusinessService::query()->create(['organization_id' => $this->organization->id, 'name' => 'Dienst ' . $name]);
        $offering = ServiceOffering::query()->create([
            'organization_id' => $this->organization->id,
            'business_service_id' => $service->id,
            'name' => 'Angebot ' . $name,
        ]);
        $item = RequestItem::query()->create([
            'organization_id' => $this->organization->id,
            'service_offering_id' => $offering->id,
            'name' => $name,
            'approval_chain' => [
                ['approver' => ['type' => 'user', 'value' => (int) $this->approverA->id]],
                ['approver' => ['type' => 'role', 'value' => 'teamleitung']],
            ],
        ]);

        return app(ServiceRequestService::class)->submit($item, $this->requester);
    }

    private function step(ServiceRequest $request, int $round, int $step): Approval {
        return $request->approvals()->where('round', $round)->where('step', $step)->orderBy('id')->firstOrFail();
    }

    public function test_superseded_steps_leave_the_inbox_and_cannot_be_decided(): void {
        $request = $this->twoStepRequest('Rundenwechsel');
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 1, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->actingAs($this->approverB)->get(route('servicedesk.approvals.index'))->assertSee('Rundenwechsel');

        $this->assertSame(2, app(ApprovalService::class)->startNextRound($request));

        // Die neue Runde trägt dieselben Stufen und Regeln, alle offen.
        $this->assertSame(['type' => 'user', 'value' => (int) $this->approverA->id], $this->step($request, 2, 1)->approver_rule);
        $this->assertSame(['type' => 'role', 'value' => 'teamleitung'], $this->step($request, 2, 2)->approver_rule);
        $this->assertSame(0, $request->approvals()->where('round', 2)->whereNotNull('decision')->count());

        // Stufe 2 der alten Runde ist abgelöst: B sieht nichts mehr, A wieder Stufe 1.
        $this->actingAs($this->approverB)->get(route('servicedesk.approvals.index'))->assertOk()->assertDontSee('Rundenwechsel');
        $this->actingAs($this->approverA)->get(route('servicedesk.approvals.index'))->assertOk()->assertSee('Rundenwechsel');

        $stale = $this->step($request, 1, 2);
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $stale), ['decision' => 'approved'])
            ->assertSessionHas('error');
        $this->assertNull($stale->fresh()->decision);

        // A entscheidet Stufe 1 erneut — dieselbe Person, neue Runde.
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame('approved', $this->step($request, 2, 1)->decision);

        // Innerhalb der Runde bleibt es bei zwei Personen.
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 2)), ['decision' => 'approved'])
            ->assertSessionHas('error');
        $this->assertNull($this->step($request, 2, 2)->decision);

        // „Alle genehmigt" zählt nur die geltende Runde — die offene alte Stufe 2 hält nichts auf.
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 2)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame(ServiceRequestStatus::Done, $request->fresh()->status);
        $this->assertNull($stale->fresh()->decision);
    }

    public function test_step_order_is_judged_within_the_current_round(): void {
        $request = $this->twoStepRequest('Reihenfolge je Runde');
        app(ApprovalService::class)->startNextRound($request);

        // Stufe 2 wartet auf Stufe 1 ihrer eigenen Runde …
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 2)), ['decision' => 'approved'])
            ->assertSessionHas('error');
        $this->assertNull($this->step($request, 2, 2)->decision);

        // … und nicht auf die nie entschiedene Stufe 1 der abgelösten Runde.
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 1)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        // Der Eingang zeigt B die Stufe 2 der geltenden Runde — die offene Stufe 1 der alten verdeckt sie nicht.
        $this->actingAs($this->approverB)->get(route('servicedesk.approvals.index'))->assertOk()->assertSee('Reihenfolge je Runde');
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $this->step($request, 2, 2)), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame(ServiceRequestStatus::Done, $request->fresh()->status);
        $this->assertSame(0, $request->approvals()->where('round', 1)->whereNotNull('decision')->count());
    }

    public function test_rejection_ends_only_its_own_round(): void {
        $request = $this->twoStepRequest('Ablehnung je Runde');
        $approvals = app(ApprovalService::class);
        $this->assertSame('rejected', $approvals->decide($this->step($request, 1, 1), $this->approverA, 'rejected', 'Kein Budget', (int) $this->requester->id));
        $this->assertTrue($approvals->chainRejected($this->step($request, 1, 2)));

        $approvals->startNextRound($request);

        $this->assertFalse($approvals->chainRejected($this->step($request, 2, 1)));
        $this->actingAs($this->approverA)->get(route('servicedesk.approvals.index'))->assertOk()->assertSee('Ablehnung je Runde');
        $this->assertSame('pending', $approvals->decide($this->step($request, 2, 1), $this->approverA, 'approved', null, (int) $this->requester->id));
        $this->assertSame('approved_all', $approvals->decide($this->step($request, 2, 2), $this->approverB, 'approved', null, (int) $this->requester->id));
    }

    public function test_next_round_copies_the_original_rule_not_the_delegation(): void {
        $request = $this->twoStepRequest('Delegation je Runde');
        $approvals = app(ApprovalService::class);
        $approvals->decide($this->step($request, 1, 1), $this->approverA, 'delegated', 'Urlaub', (int) $this->requester->id, (int) $this->approverB->id);
        $this->assertSame(3, $request->approvals()->where('round', 1)->count());
        // Eine Delegation ist kein Urteil über den Stand.
        $this->assertFalse($approvals->currentRoundHasVerdict($request));

        $approvals->startNextRound($request);

        $this->assertSame(2, $request->approvals()->where('round', 2)->count());
        $this->assertSame(['type' => 'user', 'value' => (int) $this->approverA->id], $this->step($request, 2, 1)->approver_rule);
    }

    public function test_delegation_stays_in_its_round(): void {
        $request = $this->twoStepRequest('Delegat in Runde 2');
        $approvals = app(ApprovalService::class);
        $approvals->startNextRound($request);

        $approvals->decide($this->step($request, 2, 1), $this->approverA, 'delegated', 'Urlaub', (int) $this->requester->id, (int) $this->approverB->id);

        $this->assertSame(3, $request->approvals()->where('round', 2)->count());
        $this->assertSame(2, $request->approvals()->where('round', 1)->count());
        $this->assertSame(2, $approvals->currentRound($request));
    }

    public function test_new_step_knows_round_one_without_reload_and_round_needs_a_chain(): void {
        $request = $this->twoStepRequest('Runde 1');
        $fresh = Approval::query()->create([
            'organization_id' => $this->organization->id,
            'approvable_type' => $request->getMorphClass(),
            'approvable_id' => $request->id + 1000,
            'step' => 1,
            'approver_rule' => ['type' => 'user', 'value' => (int) $this->approverA->id],
        ]);
        $this->assertSame(1, $fresh->round);
        $this->assertSame(1, app(ApprovalService::class)->currentRound($request));

        $withoutChain = new ServiceRequest;
        $withoutChain->id = $request->id + 2000;
        $this->expectException(\LogicException::class);
        app(ApprovalService::class)->startNextRound($withoutChain);
    }
}
