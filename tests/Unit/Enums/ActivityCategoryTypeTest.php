<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryTypeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Activity\ActivityCategoryType;
use Tests\TestCase;

class ActivityCategoryTypeTest extends TestCase
{
    public function test_cases(): void
    {
        $this->assertSame(
            ['admin', 'training', 'meeting', 'internal', 'travel', 'break', 'absence', 'standby', 'other'],
            ActivityCategoryType::values()
        );
        $this->assertCount(9, ActivityCategoryType::cases());
        $this->assertNull(ActivityCategoryType::tryFrom('unknown'));
        $this->assertSame(ActivityCategoryType::Break_, ActivityCategoryType::tryFrom('break'));
        $this->assertSame(ActivityCategoryType::Break_, ActivityCategoryType::tryFromName('Break_'));
    }

    public function test_labels_non_empty(): void
    {
        foreach (ActivityCategoryType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_keys_match_values(): void
    {
        $this->assertSame(ActivityCategoryType::values(), array_keys(ActivityCategoryType::options()));
    }
}
