<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaBreachScanCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Organization, ServiceTicket};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlaBreachScanCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_marks_resolution_breach_when_overdue(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $org = Organization::factory()->create();

        $breached = ServiceTicket::factory()->create([
            'organization_id' => $org->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'reaction_due_at' => '2026-06-01 11:00:00',
            'resolution_due_at' => '2026-06-01 11:30:00',
            'acknowledged_at' => '2026-06-01 10:00:00',
            'resolved_at' => null,
        ]);

        $ok = ServiceTicket::factory()->create([
            'organization_id' => $org->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'reaction_due_at' => '2026-06-01 14:00:00',
            'resolution_due_at' => '2026-06-01 18:00:00',
            'acknowledged_at' => '2026-06-01 10:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();

        $breached->refresh();
        $ok->refresh();

        $this->assertTrue($breached->resolution_breached);
        $this->assertFalse($breached->reaction_breached); // already acknowledged
        $this->assertFalse($ok->resolution_breached);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'service_ticket.sla_resolution_breached',
            'auditable_id' => $breached->id,
        ]);

        Carbon::setTestNow();
    }
}
