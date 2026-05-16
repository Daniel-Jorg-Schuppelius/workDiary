<?php

/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WithGlobalDateRange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

use Carbon\CarbonImmutable;

trait WithGlobalDateRange
{
    /**
     * Session payload that pins the global date range to a custom span.
     *
     * @return array<string, string>
     */
    protected function dateRangeSession(string|CarbonImmutable $from, string|CarbonImmutable $to): array
    {
        $from = $from instanceof CarbonImmutable ? $from : CarbonImmutable::parse($from);
        $to = $to instanceof CarbonImmutable ? $to : CarbonImmutable::parse($to);

        return [
            'ui.daterange.preset' => 'custom',
            'ui.daterange.from' => $from->toDateString(),
            'ui.daterange.to' => $to->toDateString(),
        ];
    }

    /**
     * Convenience: pin the global range to a full ISO week.
     *
     * @return array<string, string>
     */
    protected function dateRangeWeek(int $isoYear, int $isoWeek): array
    {
        $from = CarbonImmutable::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
        $to = $from->endOfWeek();

        return $this->dateRangeSession($from, $to);
    }

    /**
     * Convenience: pin the global range to a full calendar month.
     *
     * @return array<string, string>
     */
    protected function dateRangeMonth(int $year, int $month): array
    {
        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $to = $from->endOfMonth();

        return $this->dateRangeSession($from, $to);
    }

    /**
     * Convenience: pin the global range to a full calendar year.
     *
     * @return array<string, string>
     */
    protected function dateRangeYear(int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $to = $from->endOfYear();

        return $this->dateRangeSession($from, $to);
    }
}
