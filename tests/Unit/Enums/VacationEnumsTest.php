<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Vacation\VacationStatus;
use App\Enums\Vacation\VacationType;
use Tests\TestCase;

class VacationEnumsTest extends TestCase
{
    public function test_vacation_type_cases(): void
    {
        $this->assertSame(['vacation', 'sick', 'special', 'unpaid'], VacationType::values());
        $this->assertNotSame('', VacationType::Vacation->label());
        $this->assertSame(VacationType::Special, VacationType::tryFrom('special'));
    }

    public function test_vacation_status_cases(): void
    {
        $this->assertSame(['pending', 'approved', 'rejected', 'cancelled'], VacationStatus::values());
        foreach (VacationStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
            $this->assertNotSame('', $case->tone());
        }
    }

    public function test_vacation_status_tones(): void
    {
        $this->assertSame('success', VacationStatus::Approved->tone());
        $this->assertSame('error', VacationStatus::Rejected->tone());
        $this->assertSame('ghost', VacationStatus::Cancelled->tone());
        $this->assertSame('warning', VacationStatus::Pending->tone());
    }

    public function test_options_keys_match_values(): void
    {
        $this->assertSame(VacationType::values(), array_keys(VacationType::options()));
        $this->assertSame(VacationStatus::values(), array_keys(VacationStatus::options()));
    }
}
