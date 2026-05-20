<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeaveKindTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Sickness\SickLeaveKind;
use Tests\TestCase;

class SickLeaveKindTest extends TestCase
{
    public function test_cases(): void
    {
        $this->assertSame(['initial', 'follow_up'], SickLeaveKind::values());
        $this->assertCount(2, SickLeaveKind::cases());
        $this->assertNull(SickLeaveKind::tryFrom('unknown'));
        $this->assertSame(SickLeaveKind::FollowUp, SickLeaveKind::tryFromName('FollowUp'));
    }

    public function test_labels_non_empty(): void
    {
        foreach (SickLeaveKind::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_keys_match_values(): void
    {
        $this->assertSame(SickLeaveKind::values(), array_keys(SickLeaveKind::options()));
    }
}
