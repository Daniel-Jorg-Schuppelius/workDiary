<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Enums\Finance\TransferStatus;
use PHPUnit\Framework\TestCase;

class TransferStatusTest extends TestCase {
    public function test_draft_can_be_confirmed_or_voided(): void {
        $this->assertTrue(TransferStatus::Draft->canTransitionTo(TransferStatus::Confirmed));
        $this->assertTrue(TransferStatus::Draft->canTransitionTo(TransferStatus::Voided));
        $this->assertFalse(TransferStatus::Draft->canTransitionTo(TransferStatus::Transferred));
        $this->assertFalse(TransferStatus::Draft->canTransitionTo(TransferStatus::Failed));
    }

    public function test_confirmed_can_be_transferred_failed_or_voided(): void {
        $this->assertTrue(TransferStatus::Confirmed->canTransitionTo(TransferStatus::Transferred));
        $this->assertTrue(TransferStatus::Confirmed->canTransitionTo(TransferStatus::Failed));
        $this->assertTrue(TransferStatus::Confirmed->canTransitionTo(TransferStatus::Voided));
        $this->assertFalse(TransferStatus::Confirmed->canTransitionTo(TransferStatus::Draft));
    }

    public function test_failed_can_only_retry_via_confirmed(): void {
        $this->assertTrue(TransferStatus::Failed->canTransitionTo(TransferStatus::Confirmed));
        $this->assertFalse(TransferStatus::Failed->canTransitionTo(TransferStatus::Voided));
        $this->assertFalse(TransferStatus::Failed->canTransitionTo(TransferStatus::Transferred));
    }

    public function test_transferred_and_voided_are_final(): void {
        $this->assertSame([], TransferStatus::Transferred->allowedTransitions());
        $this->assertSame([], TransferStatus::Voided->allowedTransitions());
    }
}
