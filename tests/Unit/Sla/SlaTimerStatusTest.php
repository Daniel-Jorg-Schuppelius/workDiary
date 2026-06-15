<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaTimerStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Sla;

use App\Enums\ServiceTicket\{ServiceTicketStatus, SlaStatus};
use App\Models\ServiceTicket;
use App\Services\ServiceTicket\SlaTimer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlaTimerStatusTest extends TestCase {
    private SlaTimer $timer;

    protected function setUp(): void {
        parent::setUp();
        $this->timer = new SlaTimer;
    }

    private function ticket(array $attributes): ServiceTicket {
        $ticket = new ServiceTicket(array_merge([
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => 'normal',
        ], $attributes));

        return $ticket;
    }

    public function test_no_deadline_yields_none(): void {
        $ticket = $this->ticket(['reported_at' => Carbon::parse('2026-06-01 09:00:00')]);
        $this->assertSame(SlaStatus::None, $this->timer->resolutionStatus($ticket, Carbon::parse('2026-06-01 10:00:00')));
    }

    public function test_on_track_when_plenty_of_time_left(): void {
        $ticket = $this->ticket([
            'reported_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolution_due_at' => Carbon::parse('2026-06-01 19:00:00'),
        ]);
        $now = Carbon::parse('2026-06-01 10:00:00'); // 9h von 10h Restzeit
        $this->assertSame(SlaStatus::OnTrack, $this->timer->resolutionStatus($ticket, $now));
    }

    public function test_at_risk_when_remaining_below_threshold(): void {
        $ticket = $this->ticket([
            'reported_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolution_due_at' => Carbon::parse('2026-06-01 19:00:00'),
        ]);
        // 1h von 10h Restzeit = 10 % < 20 % ⇒ gefährdet.
        $now = Carbon::parse('2026-06-01 18:00:00');
        $this->assertSame(SlaStatus::AtRisk, $this->timer->resolutionStatus($ticket, $now));
    }

    public function test_breached_when_deadline_passed(): void {
        $ticket = $this->ticket([
            'reported_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolution_due_at' => Carbon::parse('2026-06-01 11:00:00'),
        ]);
        $now = Carbon::parse('2026-06-01 12:00:00');
        $this->assertSame(SlaStatus::Breached, $this->timer->resolutionStatus($ticket, $now));
    }

    public function test_met_when_resolved_before_deadline(): void {
        $ticket = $this->ticket([
            'reported_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolution_due_at' => Carbon::parse('2026-06-01 19:00:00'),
            'resolved_at' => Carbon::parse('2026-06-01 15:00:00'),
        ]);
        $this->assertSame(SlaStatus::Met, $this->timer->resolutionStatus($ticket, Carbon::parse('2026-06-02 09:00:00')));
    }

    public function test_breached_when_resolved_after_deadline(): void {
        $ticket = $this->ticket([
            'reported_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolution_due_at' => Carbon::parse('2026-06-01 11:00:00'),
            'resolved_at' => Carbon::parse('2026-06-01 13:00:00'),
        ]);
        $this->assertSame(SlaStatus::Breached, $this->timer->resolutionStatus($ticket, Carbon::parse('2026-06-01 14:00:00')));
    }

    public function test_minutes_remaining_is_negative_when_overdue(): void {
        $due = Carbon::parse('2026-06-01 11:00:00');
        $this->assertSame(-60, $this->timer->minutesRemaining($due, Carbon::parse('2026-06-01 12:00:00')));
        $this->assertSame(60, $this->timer->minutesRemaining($due, Carbon::parse('2026-06-01 10:00:00')));
        $this->assertNull($this->timer->minutesRemaining(null));
    }
}
