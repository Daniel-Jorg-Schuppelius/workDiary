<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Recurrence\RecurrenceFrequency;
use Tests\TestCase;

final class RecurrenceEnumsTest extends TestCase {
    public function test_recurrence_frequency_values(): void {
        $this->assertSame(
            ['daily', 'weekly', 'monthly', 'yearly'],
            RecurrenceFrequency::values()
        );
        $this->assertNotEmpty(RecurrenceFrequency::Daily->label());
        $this->assertNotEmpty(RecurrenceFrequency::Weekly->label());
        $this->assertNotEmpty(RecurrenceFrequency::Monthly->label());
        $this->assertNotEmpty(RecurrenceFrequency::Yearly->label());
    }
}
