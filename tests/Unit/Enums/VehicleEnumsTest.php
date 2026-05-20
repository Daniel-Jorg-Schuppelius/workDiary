<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Vehicle\VehicleOwnership;
use App\Enums\Vehicle\VehiclePropulsion;
use App\Enums\Vehicle\VehicleType;
use Tests\TestCase;

class VehicleEnumsTest extends TestCase {
    public function test_vehicle_type_cases(): void {
        $this->assertSame(['car', 'van', 'truck', 'bicycle', 'other'], VehicleType::values());
        $this->assertNull(VehicleType::tryFrom('plane'));
        foreach (VehicleType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
        $this->assertSame(VehicleType::values(), array_keys(VehicleType::options()));
    }

    public function test_vehicle_propulsion_cases_and_energy_unit(): void {
        $this->assertCount(7, VehiclePropulsion::cases());
        $this->assertSame('kwh', VehiclePropulsion::Electric->expectedEnergyUnit());
        $this->assertSame('liter', VehiclePropulsion::Diesel->expectedEnergyUnit());
        $this->assertNull(VehiclePropulsion::Muscle->expectedEnergyUnit());
        $this->assertNull(VehiclePropulsion::Other->expectedEnergyUnit());
    }

    public function test_vehicle_ownership_cases(): void {
        $this->assertSame(['owned', 'leased', 'rental'], VehicleOwnership::values());
        $this->assertSame(VehicleOwnership::Owned, VehicleOwnership::tryFromName('Owned'));
        $this->assertNull(VehicleOwnership::tryFromName('Unknown'));
    }
}
