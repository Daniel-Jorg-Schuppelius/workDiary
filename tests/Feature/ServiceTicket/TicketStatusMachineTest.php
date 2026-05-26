<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketStatusMachineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Exceptions\ServiceTicketException;
use App\Services\ServiceTicket\TicketStatusMachine;
use PHPUnit\Framework\TestCase;

class TicketStatusMachineTest extends TestCase {
    public function test_allowed_transitions(): void {
        $m = new TicketStatusMachine;
        $this->assertTrue($m->canTransition(ServiceTicketStatus::Reported, ServiceTicketStatus::Triaged));
        $this->assertTrue($m->canTransition(ServiceTicketStatus::InProgress, ServiceTicketStatus::Done));
        $this->assertTrue($m->canTransition(ServiceTicketStatus::Done, ServiceTicketStatus::Accepted));
        $this->assertTrue($m->canTransition(ServiceTicketStatus::Accepted, ServiceTicketStatus::Closed));
    }

    public function test_forbidden_transition_throws(): void {
        $m = new TicketStatusMachine;
        $this->expectException(ServiceTicketException::class);
        $m->ensureTransition(ServiceTicketStatus::Reported, ServiceTicketStatus::Closed);
    }

    public function test_closed_is_terminal(): void {
        $m = new TicketStatusMachine;
        $this->assertFalse($m->canTransition(ServiceTicketStatus::Closed, ServiceTicketStatus::Reported));
    }

    public function test_same_state_is_allowed(): void {
        $m = new TicketStatusMachine;
        $this->assertTrue($m->canTransition(ServiceTicketStatus::Reported, ServiceTicketStatus::Reported));
    }
}
