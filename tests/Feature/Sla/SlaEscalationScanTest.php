<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaEscalationScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{ServiceTicket, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SlaEscalationScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $assignee;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();

        $this->assignee = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        foreach ([NotificationEvent::SlaAtRisk, NotificationEvent::SlaBreached] as $event) {
            NotificationRule::factory()->forEvent($event)->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
            ]);
        }
    }

    private function ticket(array $attributes): ServiceTicket {
        return ServiceTicket::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'assigned_to_user_id' => $this->assignee->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'reported_at' => Carbon::now()->subHours(10),
        ]);
    }

    public function test_breached_ticket_notifies_assignee_once(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->ticket([
            'reported_at' => '2026-06-01 02:00:00',
            'resolution_due_at' => '2026-06-01 11:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->assignee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::SlaBreached->value, $data['event'] ?? null);

        Carbon::setTestNow();
    }

    public function test_at_risk_ticket_notifies_assignee(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');
        // Restzeit 1h von 10h Gesamtfrist = 10 % < 20 % ⇒ gefährdet.
        $this->ticket([
            'reported_at' => '2026-06-01 03:00:00',
            'resolution_due_at' => '2026-06-01 13:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->assignee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::SlaAtRisk->value, $data['event'] ?? null);

        Carbon::setTestNow();
    }

    public function test_breach_escalates_to_team_lead_after_threshold(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        NotificationRule::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', NotificationEvent::SlaBreached->value)
            ->update([
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'teamleitung',
            ]);

        $this->ticket([
            'reported_at' => '2026-06-01 02:00:00',
            'resolution_due_at' => '2026-06-01 11:00:00',
            'resolved_at' => null,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $teamlead->notifications()->count());

        NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::SlaBreached->value)
            ->update(['created_at' => now()->subHours(3)]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame('escalation', $data['stage'] ?? null);

        Carbon::setTestNow();
    }
}
