<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Organization, ServiceQueue, ServiceTicket, SlaClockSegment, User};
use App\Services\ServiceTicket\HelpdeskMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 065, P9 (MVP-159): Fixture-Nachrechnung (Zeiten mit Pausen-
 * Abzug, SLA-Erfüllung, Wartezeiten je Grund), Berichtsseite rendert
 * ohne Agenten-Dimension.
 */
final class HelpdeskReportTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    public function test_metrics_reproduce_hand_fixture(): void {
        $queue = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);

        // Ticket: gemeldet 09:00, reagiert 11:00 (2h), gelöst 17:00 (8h),
        // davon 1h Pause (waiting_customer) → Reaktion 1h, Lösung 7h.
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => Carbon::parse('2026-07-06 09:00:00'),
            'acknowledged_at' => Carbon::parse('2026-07-06 11:00:00'),
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
            'reaction_due_at' => Carbon::parse('2026-07-06 13:00:00'),
            'resolution_due_at' => Carbon::parse('2026-07-07 09:00:00'),
            'reaction_breached' => false,
            'resolution_breached' => false,
        ]);
        SlaClockSegment::query()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $ticket->id,
            'target' => SlaClockSegment::TARGET_RESOLUTION,
            'paused_from' => Carbon::parse('2026-07-06 12:00:00'),
            'paused_to' => Carbon::parse('2026-07-06 13:00:00'),
            'reason' => 'waiting_customer',
        ]);

        $metrics = app(HelpdeskMetricsService::class);
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-31');

        $times = $metrics->responseTimes($from, $to);
        $this->assertSame(1.0, $times['reaction']['p50'], 'Reaktion 2h minus 1h Pause.');
        $this->assertSame(7.0, $times['resolution']['p50'], 'Lösung 8h minus 1h Pause.');

        $compliance = $metrics->slaCompliance($from, $to);
        $this->assertSame(100.0, $compliance['reaction_met']);
        $this->assertSame(1, $compliance['total']);

        $waiting = $metrics->waitingByReason($from, $to);
        $this->assertSame(1.0, $waiting['waiting_customer']);

        $volume = $metrics->volumeByQueue($from, $to);
        $week = Carbon::parse('2026-07-06')->format('o-\WW');
        $this->assertSame(1, $volume[$week]['Support']);
    }

    public function test_report_page_renders_without_agent_dimension(): void {
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
        ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'assigned_to_user_id' => $this->agent->id,
            'reported_at' => now()->subDay(),
        ]);

        // Keine Agenten-Ranglisten: der Service kennt schlicht keine
        // Agenten-Dimension (kleinste Aggregation = Queue, Vorgabe 065);
        // die Seite rendert alle Kennzahl-Blöcke.
        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.index'))
            ->assertOk()
            ->assertSee(__('Helpdesk-Bericht'))
            ->assertSee(__('Ticketvolumen je Woche'))
            ->assertSee(__('Wartezeiten nach Verursacher'))
            ->assertSee(__('Katalog-Nachfrage'));
    }
}
