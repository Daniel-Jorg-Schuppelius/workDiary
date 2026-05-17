<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DailyBalance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

/**
 * Read-only DTO returned by {@see WorkBalanceCalculator::daily()}.
 */
final class DailyBalance
{
    /**
     * @param  array<string, int>  $byActivity  minutes grouped by TimeEntry::activity_type
     * @param  array<string, int>  $byKind  minutes grouped by TimeEntry::kind
     */
    public function __construct(
        public readonly string $date,
        public readonly int $targetMinutes,
        public readonly int $attendanceMinutes,
        public readonly int $breakMinutes,
        public readonly int $trackedMinutes,
        public readonly int $untrackedMinutes,
        public readonly int $balanceMinutes,
        public readonly array $byActivity,
        public readonly array $byKind,
    ) {}
}
