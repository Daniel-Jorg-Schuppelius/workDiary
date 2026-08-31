<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{BusinessService, Organization, RequestItem, ServiceOffering, ServiceQueue, ServiceRequest, Task, User};
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P4 (MVP-154): Snapshot-Einfrierung (Katalogänderung
 * schreibt nie um), mehrstufige Genehmigung mit Selbstfreigabe-Sperre,
 * Fulfillment idempotent, serverseitige Sichtbarkeit.
 */
final class HelpdeskCatalogTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $requester;

    private User $approver;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->requester = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        $this->approver = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Default', 'is_default' => true]);
    }

    /** @param array<string, mixed> $overrides */
    private function item(array $overrides = []): RequestItem {
        $service = BusinessService::query()->create(['organization_id' => $this->org->id, 'name' => 'Arbeitsplatz']);
        $offering = ServiceOffering::query()->create([
            'organization_id' => $this->org->id,
            'business_service_id' => $service->id,
            'name' => 'Hardware',
        ]);

        return RequestItem::query()->create([
            'organization_id' => $this->org->id,
            'service_offering_id' => $offering->id,
            'name' => 'Notebook bestellen',
            'fulfillment' => 'task',
            ...$overrides,
        ]);
    }

    public function test_submit_freezes_snapshots_against_catalog_changes(): void {
        $item = $this->item(['approval_chain' => [['approver' => ['type' => 'role', 'value' => 'teamleitung']]]]);
        $request = app(ServiceRequestService::class)->submit($item, $this->requester, ['cpu' => 'i7']);

        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->status);
        $this->assertSame('Notebook bestellen', $request->catalog_snapshot['name']);
        $this->assertSame(['cpu' => 'i7'], $request->form_snapshot['answers']);
        $this->assertSame('service_request', $request->ticket()->firstOrFail()->kind->value);

        // Katalogänderung schreibt NIE um.
        $item->update(['name' => 'Umbenannt', 'version' => 2]);
        $this->assertSame('Notebook bestellen', $request->fresh()->catalog_snapshot['name']);
        $this->assertSame(1, $request->fresh()->catalog_snapshot['version']);
    }

    public function test_multi_step_approval_with_self_approval_block(): void {
        $item = $this->item(['approval_chain' => [
            ['approver' => ['type' => 'role', 'value' => 'teamleitung']],
            ['approver' => ['type' => 'user', 'value' => 99]],
        ]]);
        $service = app(ServiceRequestService::class);
        $request = $service->submit($item, $this->requester);
        $steps = $request->approvals()->get();
        $this->assertCount(2, $steps);

        // Selbstfreigabe gesperrt.
        try {
            $service->decide($steps[0], $this->requester, 'approved');
            $this->fail('Selbstfreigabe wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Schritt 1 genehmigt → Request bleibt pending (Schritt 2 offen).
        $request = $service->decide($steps[0], $this->approver, 'approved');
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->status);

        // Schritt 2 genehmigt → approved + Fulfillment (Task). Zweite
        // Person: wer Schritt 1 entschieden hat, ist für Schritt 2 gesperrt
        // (Sicherheitsscan 2026-08-23, S-34).
        $zweiterGenehmiger = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        $request = $service->decide($steps[1]->fresh(), $zweiterGenehmiger, 'approved');
        $this->assertSame(ServiceRequest::STATUS_DONE, $request->status);
        $this->assertNotNull($request->fulfilled_id);
    }

    public function test_rejection_requires_reason_and_stops_request(): void {
        $item = $this->item(['approval_chain' => [['approver' => ['type' => 'role', 'value' => 'teamleitung']]]]);
        $service = app(ServiceRequestService::class);
        $request = $service->submit($item, $this->requester);
        $step = $request->approvals()->firstOrFail();

        try {
            $service->decide($step, $this->approver, 'rejected', '');
            $this->fail('Ablehnung ohne Grund wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        $request = $service->decide($step, $this->approver, 'rejected', 'Kein Budget');
        $this->assertSame(ServiceRequest::STATUS_REJECTED, $request->status);
        $this->assertNull($request->fulfilled_id);
    }

    public function test_fulfillment_is_idempotent(): void {
        $item = $this->item(); // keine Kette → direkt erfüllt
        $service = app(ServiceRequestService::class);
        $request = $service->submit($item, $this->requester);

        $this->assertSame(ServiceRequest::STATUS_DONE, $request->status);
        $taskCount = Task::query()->count();

        $service->fulfill($request->fresh(), $this->approver);
        $this->assertSame($taskCount, Task::query()->count(), 'Zweiter fulfill-Aufruf erzeugt nichts Neues.');
    }

    public function test_visibility_filters_by_role(): void {
        $this->item(['visibility' => ['roles' => ['admin']], 'name' => 'Nur Admin']);
        $visible = app(ServiceRequestService::class)->visibleItems($this->requester)->pluck('name');

        $this->assertFalse($visible->contains('Nur Admin'));
    }
}
