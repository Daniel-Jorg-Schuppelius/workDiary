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
use App\Models\{Organization, ServiceQueue, ServiceTicket, SlaClockSegment, TicketSatisfaction, User};
use App\Services\ServiceTicket\HelpdeskMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 065, P9 (MVP-159): Fixture-Nachrechnung (Zeiten mit Pausen-
 * Abzug, SLA-Erfüllung, Wartezeiten je Grund, Aging-Bänder, FCR mit
 * getrennter Wiederöffnungs-/Weiterleitungsquote, Zufriedenheit inkl.
 * Rücklaufquote), Berichtsseite rendert ohne Agenten-Dimension.
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

    public function test_metric_version_is_three_after_mvp338(): void {
        // DoD MVP-159: neue Kennzahlen = neue Definitionsversion —
        // v3 seit MVP-338 (recurringDespiteArticle, Bauturbo A20).
        $this->assertSame(3, HelpdeskMetricsService::METRIC_VERSION);
    }

    public function test_aging_histogram_reproduces_hand_fixture(): void {
        $support = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
        $backoffice = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Backoffice']);

        // Offene Tickets: 12h → 0–1, 2d → 1–3 (Backoffice), 5d → 3–7,
        // 10d → 7–30, 40d → >30. Gelöstes 10d-Ticket zählt NICHT.
        foreach ([
            ['reported_at' => now()->subHours(12), 'queue_id' => $support->id],
            ['reported_at' => now()->subDays(2), 'queue_id' => $backoffice->id],
            ['reported_at' => now()->subDays(5), 'queue_id' => $support->id],
            ['reported_at' => now()->subDays(10), 'queue_id' => $support->id],
            ['reported_at' => now()->subDays(40), 'queue_id' => $support->id],
        ] as $attributes) {
            ServiceTicket::factory()->create([
                'organization_id' => $this->org->id,
                'status' => ServiceTicketStatus::InProgress,
                ...$attributes,
            ]);
        }
        ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $support->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => now()->subDays(10),
            'resolved_at' => now()->subDay(),
        ]);

        $aging = app(HelpdeskMetricsService::class)->agingHistogram();

        $this->assertSame(['0-1', '1-3', '3-7', '7-30', '>30'], array_keys($aging));
        $this->assertSame(1, $aging['0-1']['total']);
        $this->assertSame(1, $aging['1-3']['total']);
        $this->assertSame(1, $aging['3-7']['total']);
        $this->assertSame(1, $aging['7-30']['total'], 'Gelöstes Ticket zählt nicht ins Aging.');
        $this->assertSame(1, $aging['>30']['total']);
        $this->assertSame(['Backoffice' => 1], $aging['1-3']['queues'], 'Queue ist die kleinste Aggregation.');
    }

    public function test_fcr_excludes_reopened_and_requeued_tickets(): void {
        $queue = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
        $resolved = [
            'organization_id' => $this->org->id,
            'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => Carbon::parse('2026-07-06 09:00:00'),
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ];

        $clean = ServiceTicket::factory()->create($resolved);
        $reopened = ServiceTicket::factory()->create($resolved);
        $reopened->audit('service_ticket.reopened', ['reason' => 'Nicht behoben']);
        $requeued = ServiceTicket::factory()->create($resolved);
        $requeued->audit('service_ticket.requeued', ['from' => 'Support', 'to' => 'Backoffice']);

        // Fremd-Org-Ticket mit Reopen-Audit darf NIE einfließen.
        $foreignOrg = Organization::factory()->create();
        $foreign = ServiceTicket::factory()->create([...$resolved, 'organization_id' => $foreignOrg->id, 'queue_id' => null]);
        $foreign->audit('service_ticket.reopened', ['reason' => 'fremd']);

        $fcr = app(HelpdeskMetricsService::class)
            ->fcrAndReopens(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $this->assertSame(3, $fcr['total']);
        $this->assertSame(1, $fcr['fcr'], 'Nur das Ticket ohne reopened-/requeued-Audit ist FCR.');
        $this->assertSame(33.3, $fcr['fcr_rate']);
        $this->assertSame(1, $fcr['reopened']);
        $this->assertSame(33.3, $fcr['reopened_rate']);
        $this->assertSame(1, $fcr['requeued'], 'Weiterleitung wird getrennt ausgewiesen.');
        $this->assertSame(33.3, $fcr['requeued_rate']);
        $this->assertSame(
            ['total' => 3, 'fcr' => 1, 'reopened' => 1, 'requeued' => 1, 'fcr_rate' => 33.3],
            $fcr['queues']['Support'],
        );
        $this->assertTrue($clean->exists, 'FCR-Fixture (ohne Audits) ist angelegt.');
    }

    public function test_satisfaction_metrics_include_response_rate(): void {
        $queue = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
        $resolved = [
            'organization_id' => $this->org->id,
            'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => Carbon::parse('2026-07-06 09:00:00'),
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ];
        $rated = ServiceTicket::factory()->create($resolved);
        ServiceTicket::factory()->create($resolved); // gelöst, unbewertet

        TicketSatisfaction::query()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $rated->id,
            'score' => 5,
            'comment' => 'Top',
            'answered_at' => Carbon::parse('2026-07-07 08:00:00'),
        ]);
        // Antwort außerhalb des Zeitraums zählt nicht.
        $old = ServiceTicket::factory()->create([...$resolved, 'resolved_at' => Carbon::parse('2026-05-01 12:00:00')]);
        TicketSatisfaction::query()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $old->id,
            'score' => 1,
            'answered_at' => Carbon::parse('2026-05-02 08:00:00'),
        ]);

        $satisfaction = app(HelpdeskMetricsService::class)
            ->satisfaction(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $this->assertSame(5.0, $satisfaction['average']);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1], $satisfaction['distribution']);
        $this->assertSame(1, $satisfaction['responses']);
        $this->assertSame(2, $satisfaction['closed_total'], 'Basis: gelöste Tickets im Zeitraum.');
        $this->assertSame(50.0, $satisfaction['response_rate']);
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
