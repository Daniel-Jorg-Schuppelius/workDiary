<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskApprovalInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{Approval, BusinessService, Organization, RequestItem, ServiceOffering, ServiceQueue, ServiceRequest, User};
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-154: Genehmigungs-Inbox — nur der zuständige Approver
 * sieht den NIEDRIGSTEN offenen Schritt (user-Id bzw. Rolle),
 * approve/reject/question via HTTP, Delegation erzeugt einen neuen offenen
 * Schritt gleicher Nummer (Selbstfreigabe-Sperre gilt auch für den
 * Delegaten), fremde Organisationen enden 404.
 */
final class HelpdeskApprovalInboxTest extends TestCase {
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

    /** @param array<int, array<string, mixed>> $chain */
    private function submittedRequest(array $chain, string $name = 'Notebook bestellen'): ServiceRequest {
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
            'approval_chain' => $chain,
        ]);

        return app(ServiceRequestService::class)->submit($item, $this->requester);
    }

    /** Zweistufige Kette: Schritt 1 persönlich (A), Schritt 2 Rolle. */
    private function twoStepRequest(string $name = 'Notebook bestellen'): ServiceRequest {
        return $this->submittedRequest([
            ['approver' => ['type' => 'user', 'value' => (int) $this->approverA->id]],
            ['approver' => ['type' => 'role', 'value' => 'teamleitung']],
        ], $name);
    }

    public function test_only_responsible_approver_sees_lowest_open_step(): void {
        $this->twoStepRequest('Inbox-Sichtbarkeit');

        // Schritt 1 ist user-gebunden an A — nur A sieht ihn.
        $this->actingAs($this->approverA)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertSee('Inbox-Sichtbarkeit');

        // B hat das Recht und die Rolle für Schritt 2 — der ist aber noch
        // nicht der niedrigste offene Schritt.
        $this->actingAs($this->approverB)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertDontSee('Inbox-Sichtbarkeit');

        // Ohne service_request.approve: 403.
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->get(route('servicedesk.approvals.index'))->assertForbidden();
    }

    public function test_approve_flow_advances_chain_via_http(): void {
        $request = $this->twoStepRequest('Freigabe-Durchlauf');
        $step1 = $request->approvals()->where('step', 1)->firstOrFail();

        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->fresh()->status);

        // Jetzt ist Schritt 2 (Rolle) der niedrigste offene — B sieht und entscheidet.
        $this->actingAs($this->approverB)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertSee('Freigabe-Durchlauf');

        $step2 = $request->approvals()->where('step', 2)->firstOrFail();
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $step2), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame(ServiceRequest::STATUS_DONE, $request->fresh()->status);
    }

    public function test_higher_step_cannot_be_decided_before_lower(): void {
        $request = $this->twoStepRequest('Reihenfolge');
        $step2 = $request->approvals()->where('step', 2)->firstOrFail();

        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $step2), ['decision' => 'approved'])
            ->assertSessionHas('error');

        $this->assertNull($step2->fresh()->decision);
    }

    public function test_reject_requires_reason_and_stops_request(): void {
        $request = $this->twoStepRequest('Ablehnung');
        $step1 = $request->approvals()->where('step', 1)->firstOrFail();

        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'rejected'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), [
                'decision' => 'rejected',
                'reason' => 'Kein Budget',
            ])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame(ServiceRequest::STATUS_REJECTED, $request->fresh()->status);
    }

    public function test_question_keeps_step_open(): void {
        $request = $this->twoStepRequest('Rückfrage');
        $step1 = $request->approvals()->where('step', 1)->firstOrFail();

        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), [
                'decision' => 'question',
                'reason' => 'Welche Ausstattung genau?',
            ])
            ->assertRedirect(route('servicedesk.approvals.index'));

        // question zählt NICHT als erledigt: Request bleibt pending, der
        // Schritt bleibt in der Inbox sichtbar und erneut entscheidbar.
        $this->assertSame('question', $step1->fresh()->decision);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->fresh()->status);
        $this->actingAs($this->approverA)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertSee('Rückfrage');
    }

    public function test_delegation_creates_new_step_and_blocks_requester_as_delegate(): void {
        $request = $this->twoStepRequest('Delegation');
        $step1 = $request->approvals()->where('step', 1)->firstOrFail();

        // Delegation an den Antragsteller: Selbstfreigabe-Sperre greift.
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), [
                'decision' => 'delegated',
                'reason' => 'Bitte übernehmen',
                'delegate' => $this->requester->sqid,
            ])
            ->assertSessionHas('error');
        $this->assertNull($step1->fresh()->decision);

        // Delegation an B: alter Schritt 'delegated', neuer offener Schritt
        // GLEICHER Nummer mit user-Regel auf B, Request bleibt pending.
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), [
                'decision' => 'delegated',
                'reason' => 'Urlaub — bitte übernehmen',
                'delegate' => $this->approverB->sqid,
            ])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $this->assertSame('delegated', $step1->fresh()->decision);
        $delegated = Approval::query()
            ->where('approvable_type', ServiceRequest::class)
            ->where('approvable_id', $request->id)
            ->where('step', 1)
            ->whereNull('decision')
            ->firstOrFail();
        $this->assertSame(['type' => 'user', 'value' => (int) $this->approverB->id], $delegated->approver_rule);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->fresh()->status);

        // Der Delegat sieht den Schritt und kann ihn entscheiden.
        $this->actingAs($this->approverB)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertSee('Delegation');

        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $delegated), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->fresh()->status); // Schritt 2 noch offen
    }

    public function test_delegated_step_still_blocks_requester_self_approval(): void {
        // Kette, deren Schritt regelseitig auf den ANTRAGSTELLER zeigt —
        // die Sperre muss bei der Entscheidung greifen (Defense in Depth).
        $request = $this->submittedRequest([
            ['approver' => ['type' => 'user', 'value' => (int) $this->requester->id]],
        ], 'Selbstfreigabe');
        $step = $request->approvals()->firstOrFail();

        $this->actingAs($this->requester)
            ->post(route('servicedesk.approvals.decide', $step), ['decision' => 'approved'])
            ->assertSessionHas('error');
        $this->assertNull($step->fresh()->decision);
    }

    public function test_unresponsible_approver_cannot_decide(): void {
        $request = $this->twoStepRequest('Zuständigkeit');
        $step1 = $request->approvals()->where('step', 1)->firstOrFail();

        // B ist für Schritt 1 (user=A) nicht zuständig.
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_foreign_org_approval_is_not_reachable(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignApproval = Approval::query()->create([
            'organization_id' => $foreignOrg->id,
            'approvable_type' => ServiceRequest::class,
            'approvable_id' => 999,
            'step' => 1,
            'approver_rule' => ['type' => 'user', 'value' => (int) $this->approverA->id],
        ]);

        $this->actingAs($this->approverA)
            ->get(route('servicedesk.approvals.decide-form', $foreignApproval->sqid))
            ->assertNotFound();
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $foreignApproval->sqid), ['decision' => 'approved'])
            ->assertNotFound();
    }
}
