<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryActivityTypeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\TimeEntry\TimeEntryActivityType;
use Tests\TestCase;

final class TimeEntryActivityTypeTest extends TestCase {
    public function test_values(): void {
        $this->assertSame(
            ['project', 'admin', 'training', 'meeting', 'internal', 'travel', 'break', 'absence', 'standby', 'other'],
            TimeEntryActivityType::values()
        );
        foreach (TimeEntryActivityType::cases() as $c) {
            $this->assertNotEmpty($c->label());
        }
    }
}
