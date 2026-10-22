<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskRoutingAssignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, Team, User};
use App\Models\ServiceTicket\{BusinessService, ServiceQueue, ServiceTicket, TicketRoutingRule};
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Routing-Bedingungen Fachdienst, Sprache und Geschäftszeit sowie Zuweisung
 * reihum oder nach Auslastung (MVP-1258).
 */
final class HelpdeskRoutingAssignmentTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->agent = User::factory()->teamleitung()->create();
        $this->org = $this->agent->organization;
        app()->instance('currentOrganization', $this->org);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $conditions @param array<string, mixed> $actions */
    private function rule(array $conditions, array $actions, int $position = 1): void {
        TicketRoutingRule::query()->create([
            'organization_id' => $this->org->id, 'name' => 'Regel ' . $position, 'position' => $position,
            'conditions' => $conditions, 'actions' => $actions,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function ticket(array $payload = []): ServiceTicket {
        return app(ServiceTicketService::class)->create($this->org, $this->agent, ['title' => 'Störung', ...$payload]);
    }

    public function test_service_and_locale_conditions_route(): void {
        $mail = BusinessService::query()->create(['organization_id' => $this->org->id, 'name' => 'E-Mail']);
        $this->rule(['business_service_id' => $mail->id], ['set_priority' => 'high'], 1);
        $this->rule(['locale' => 'fr'], ['set_priority' => 'low'], 2);
        $french = Customer::factory()->create(['organization_id' => $this->org->id, 'document_locale' => 'fr']);

        $this->assertSame('high', $this->ticket(['business_service_id' => $mail->id])->priority->value);
        $this->assertSame('low', $this->ticket(['customer_id' => $french->id])->priority->value);
        $this->assertSame('normal', $this->ticket()->priority->value);
    }

    public function test_business_hours_condition_uses_the_queue_windows_in_org_time(): void {
        ServiceQueue::query()->create([
            'organization_id' => $this->org->id, 'name' => 'Standard', 'is_default' => true,
            'business_hours' => [['weekday' => 3, 'from' => '08:00', 'to' => '17:00']],
        ]);
        $this->rule(['business_hours' => 'out'], ['set_priority' => 'low']);
        $tz = \App\Support\Tz::ofOrganization($this->org);

        // Mittwoch 2026-10-21, 10:00 bzw. 20:00 Ortszeit.
        $inside = $this->ticket(['reported_at' => Carbon::parse('2026-10-21 10:00', $tz)->utc()->toDateTimeString()]);
        $outside = $this->ticket(['reported_at' => Carbon::parse('2026-10-21 20:00', $tz)->utc()->toDateTimeString()]);

        $this->assertSame('normal', $inside->priority->value);
        $this->assertSame('low', $outside->priority->value);
    }

    public function test_round_robin_and_least_loaded_pick_from_the_queue_team(): void {
        $team = Team::factory()->create(['organization_id' => $this->org->id]);
        [$anna, $bert] = [User::factory()->user()->create(['organization_id' => $this->org->id]), User::factory()->user()->create(['organization_id' => $this->org->id])];
        $gone = User::factory()->user()->create(['organization_id' => $this->org->id, 'deactivated_at' => now()]);
        $team->members()->attach([$anna->id, $bert->id, $gone->id]);
        $queue = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Team', 'is_default' => true, 'team_id' => $team->id]);
        $this->rule(['queue_id' => $queue->id], ['assign' => 'round_robin']);

        $assigned = [$this->ticket()->assigned_to_user_id, $this->ticket()->assigned_to_user_id, $this->ticket()->assigned_to_user_id];
        $this->assertSame([$anna->id, $bert->id, $anna->id], array_map('intval', $assigned));

        TicketRoutingRule::query()->update(['actions' => ['assign' => 'least_loaded']]);
        ServiceTicket::query()->where('assigned_to_user_id', $anna->id)->limit(1)->update(['status' => ServiceTicketStatus::Closed->value]);
        // Anna hat noch 1 offenes, Bert 1 → Gleichstand, kleinere ID gewinnt; danach Bert.
        $this->assertSame($anna->id, (int) $this->ticket()->assigned_to_user_id);
        $this->assertSame($bert->id, (int) $this->ticket()->assigned_to_user_id);
    }
}
