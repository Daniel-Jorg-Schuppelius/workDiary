<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Tour\TourStatus;
use Tests\TestCase;

class TourStatusTest extends TestCase {
    public function test_cases(): void {
        $this->assertSame(
            ['draft', 'planned', 'in_progress', 'completed', 'cancelled'],
            TourStatus::values()
        );
        $this->assertCount(5, TourStatus::cases());
        $this->assertNull(TourStatus::tryFrom('unknown'));
        $this->assertSame(TourStatus::InProgress, TourStatus::tryFromName('InProgress'));
    }

    public function test_labels_non_empty(): void {
        foreach (TourStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_options_keys_match_values(): void {
        $this->assertSame(TourStatus::values(), array_keys(TourStatus::options()));
    }
}
