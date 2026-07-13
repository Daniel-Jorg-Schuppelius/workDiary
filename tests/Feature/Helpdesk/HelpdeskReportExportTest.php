<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskReportExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{AuditLog, Organization, ServiceQueue, ServiceTicket, TicketSatisfaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Feature 065, MVP-159: Drilldown nur signiert (403 ohne Signatur),
 * Summen-Konsistenz sichtbar, org-gescopte Trefferlisten (Fremd-Org-Daten
 * erscheinen nie), CSV-Export mit Exportkopf (metric_version) + Audit
 * report.exported, PDF-Download übers pdf-toolkit.
 */
final class HelpdeskReportExportTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    private ServiceQueue $queue;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        $this->queue = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Support', 'is_default' => true]);
    }

    public function test_drilldown_requires_valid_signature(): void {
        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.drilldown', ['kind' => 'aging_band', 'key' => '0-1', 'expected' => 0]))
            ->assertForbidden();
    }

    public function test_volume_drilldown_is_org_scoped(): void {
        $own = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'ticket_no' => 'ST-2026-EIGEN',
            'reported_at' => now()->subHour(),
        ]);
        $foreignOrg = Organization::factory()->create();
        ServiceTicket::factory()->create([
            'organization_id' => $foreignOrg->id,
            'ticket_no' => 'ST-2026-FREMD',
            'reported_at' => now()->subHour(),
        ]);

        $week = $own->reported_at->format('o-\WW');
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'volume_week_queue', 'key' => $week, 'expected' => 1,
            'from' => now()->subWeek()->toDateString(), 'to' => now()->toDateString(),
        ]);

        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-2026-EIGEN')
            ->assertDontSee('ST-2026-FREMD')
            ->assertDontSee(__('Konsistenz-Hinweis'));
    }

    public function test_aging_band_drilldown_shows_consistency_hint_on_mismatch(): void {
        ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'ticket_no' => 'ST-2026-AGING',
            'status' => ServiceTicketStatus::InProgress,
            'reported_at' => now()->subDays(2),
        ]);

        // Passender Erwartungswert: kein Hinweis, Treffer im Band 1–3.
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'aging_band', 'key' => '1-3', 'expected' => 1,
        ]);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-2026-AGING')
            ->assertDontSee(__('Konsistenz-Hinweis'));

        // Falscher Erwartungswert → sichtbarer Konsistenz-Hinweis.
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'aging_band', 'key' => '1-3', 'expected' => 5,
        ]);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('Konsistenz-Hinweis');

        // Unbekanntes Band → 404.
        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'aging_band', 'key' => '99-100', 'expected' => 0,
        ]);
        $this->actingAs($this->agent)->get($url)->assertNotFound();
    }

    public function test_reopened_and_requeued_drilldowns_are_separated(): void {
        $resolved = [
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => Carbon::parse('2026-07-06 09:00:00'),
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ];
        $reopened = ServiceTicket::factory()->create([...$resolved, 'ticket_no' => 'ST-2026-REOPEN']);
        $reopened->audit('service_ticket.reopened', ['reason' => 'Nicht behoben']);
        $requeued = ServiceTicket::factory()->create([...$resolved, 'ticket_no' => 'ST-2026-REQUEUE']);
        $requeued->audit('service_ticket.requeued', ['from' => 'Support', 'to' => 'Backoffice']);
        ServiceTicket::factory()->create([...$resolved, 'ticket_no' => 'ST-2026-FCR']);

        $params = ['from' => '2026-07-01', 'to' => '2026-07-31', 'kind' => 'reopened', 'expected' => 1];

        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [...$params, 'key' => 'reopened']);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-2026-REOPEN')
            ->assertDontSee('ST-2026-REQUEUE')
            ->assertDontSee('ST-2026-FCR');

        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [...$params, 'key' => 'requeued']);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-2026-REQUEUE')
            ->assertDontSee('ST-2026-REOPEN');
    }

    public function test_satisfaction_score_drilldown_is_org_scoped(): void {
        $own = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'ticket_no' => 'ST-2026-RATED',
            'status' => ServiceTicketStatus::Done,
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ]);
        TicketSatisfaction::query()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $own->id,
            'score' => 5,
            'comment' => 'Top',
            'answered_at' => Carbon::parse('2026-07-07 08:00:00'),
        ]);
        $foreignOrg = Organization::factory()->create();
        $foreign = ServiceTicket::factory()->create([
            'organization_id' => $foreignOrg->id,
            'ticket_no' => 'ST-2026-XRATED',
            'status' => ServiceTicketStatus::Done,
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ]);
        TicketSatisfaction::query()->create([
            'organization_id' => $foreignOrg->id,
            'service_ticket_id' => $foreign->id,
            'score' => 5,
            'answered_at' => Carbon::parse('2026-07-07 08:00:00'),
        ]);

        $url = URL::temporarySignedRoute('helpdesk.reports.drilldown', now()->addMinutes(30), [
            'kind' => 'satisfaction_score', 'key' => '5', 'expected' => 1,
            'from' => '2026-07-01', 'to' => '2026-07-31',
        ]);
        $this->actingAs($this->agent)->get($url)
            ->assertOk()
            ->assertSee('ST-2026-RATED')
            ->assertSee('Top')
            ->assertDontSee('ST-2026-XRATED');
    }

    public function test_csv_export_has_metric_version_header_and_audit(): void {
        ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => Carbon::parse('2026-07-06 09:00:00'),
            'resolved_at' => Carbon::parse('2026-07-06 17:00:00'),
        ]);

        $response = $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.csv', ['metric' => 'fcr', 'from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('#report:helpdesk_fcr_v3', $content);
        $this->assertStringContainsString('metric_version', $content);
        $this->assertStringContainsString('fcr_rate_percent', $content);

        $audit = AuditLog::query()->where('event', 'report.exported')->get();
        $this->assertCount(1, $audit);
        $this->assertSame('helpdesk_fcr', $audit->first()->getAttribute('changes')['report_code']);

        // Unbekannte Kennzahl → 404 (Whitelist).
        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.csv', ['metric' => 'unbekannt']))
            ->assertNotFound();
    }

    public function test_pdf_export_downloads_via_pdf_toolkit(): void {
        ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'queue_id' => $this->queue->id,
            'status' => ServiceTicketStatus::Done,
            'reported_at' => now()->subDay(),
            'resolved_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->agent)
            ->get(route('helpdesk.reports.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(1, AuditLog::query()->where('event', 'report.exported')->count());
    }
}
