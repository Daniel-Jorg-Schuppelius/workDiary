<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusMachineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Asset;

use App\Enums\Asset\AssetStatus;
use App\Exceptions\AssetValidationException;
use App\Services\Asset\AssetStatusMachine;
use PHPUnit\Framework\TestCase;

class AssetStatusMachineTest extends TestCase {
    public function test_allows_valid_transition(): void {
        $machine = new AssetStatusMachine;

        $this->assertTrue($machine->canTransition(AssetStatus::Active, AssetStatus::InRepair));
        $machine->ensureTransition(AssetStatus::Active, AssetStatus::InRepair);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_invalid_transition(): void {
        $machine = new AssetStatusMachine;

        $this->assertFalse($machine->canTransition(AssetStatus::Decommissioned, AssetStatus::Active));
        $this->expectException(AssetValidationException::class);

        $machine->ensureTransition(AssetStatus::Decommissioned, AssetStatus::Active);
    }

    public function test_same_status_is_allowed(): void {
        $machine = new AssetStatusMachine;

        $this->assertTrue($machine->canTransition(AssetStatus::Active, AssetStatus::Active));
    }
}
