<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolationDetectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Enums\ServiceTicket\{ServiceTicketStatus, SlaViolationKind};
use App\Models\{Organization, ServiceTicket, SlaViolation};
use App\Services\ServiceTicket\SlaViolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlaViolationDetectionTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    public function test_scan_records_exactly_one_resolution_violation_idempotently(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'resolution_due_at' => '2026-06-01 11:00:00',
            'acknowledged_at' => '2026-06-01 09:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();
        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();

        $violations = SlaViolation::query()
            ->where('service_ticket_id', $ticket->id)
            ->where('kind', SlaViolationKind::ResolutionTime->value)
            ->get();

        $this->assertCount(1, $violations);
        $this->assertSame(60, $violations->first()->overdue_minutes);
        $this->assertSame('normal', $violations->first()->priority);

        Carbon::setTestNow();
    }

    public function test_scan_records_response_violation_when_unacknowledged(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::Reported->value,
            'reaction_due_at' => '2026-06-01 10:00:00',
            'acknowledged_at' => null,
        ]);

        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();

        $this->assertDatabaseHas('sla_violations', [
            'service_ticket_id' => $ticket->id,
            'kind' => SlaViolationKind::ResponseTime->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_on_time_ticket_yields_no_violation(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'reaction_due_at' => '2026-06-01 14:00:00',
            'resolution_due_at' => '2026-06-01 18:00:00',
            'acknowledged_at' => '2026-06-01 09:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();

        $this->assertSame(0, SlaViolation::query()->where('service_ticket_id', $ticket->id)->count());

        Carbon::setTestNow();
    }

    public function test_service_acknowledge_sets_cause_and_actor(): void {
        $service = app(SlaViolationService::class);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->org->id]);
        $actor = \App\Models\User::factory()->create(['organization_id' => $this->org->id]);

        $violation = SlaViolation::factory()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $ticket->id,
        ]);

        $service->acknowledge($violation, $actor, 'Materialengpass');
        $violation->refresh();

        $this->assertNotNull($violation->acknowledged_at);
        $this->assertSame($actor->id, $violation->acknowledged_by);
        $this->assertSame('Materialengpass', $violation->cause);

        // Idempotent: zweiter Aufruf ändert nichts.
        $first = $violation->acknowledged_at;
        $service->acknowledge($violation->refresh(), $actor, 'andere Ursache');
        $this->assertSame($first->toIso8601String(), $violation->refresh()->acknowledged_at->toIso8601String());
        $this->assertSame('Materialengpass', $violation->cause);
    }
}
