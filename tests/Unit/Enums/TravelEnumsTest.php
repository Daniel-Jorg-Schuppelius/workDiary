<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Travel\TravelLogVehicle;
use Tests\TestCase;

final class TravelEnumsTest extends TestCase {
    public function test_travel_log_vehicle_values(): void {
        $this->assertSame(
            ['company', 'private', 'rental', 'public_transport', 'bicycle', 'foot', 'other'],
            TravelLogVehicle::values()
        );
        foreach (TravelLogVehicle::cases() as $c) {
            $this->assertNotEmpty($c->label());
        }
    }
}
