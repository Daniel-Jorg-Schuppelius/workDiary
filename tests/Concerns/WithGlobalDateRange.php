<?php

namespace Tests\Concerns;

use Carbon\CarbonImmutable;

trait WithGlobalDateRange {
    /**
     * Session payload that pins the global date range to a custom span.
     *
     * @return array<string, string>
     */
    protected function dateRangeSession(string|CarbonImmutable $from, string|CarbonImmutable $to): array {
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
    protected function dateRangeWeek(int $isoYear, int $isoWeek): array {
        $from = CarbonImmutable::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
        $to = $from->endOfWeek();

        return $this->dateRangeSession($from, $to);
    }

    /**
     * Convenience: pin the global range to a full calendar month.
     *
     * @return array<string, string>
     */
    protected function dateRangeMonth(int $year, int $month): array {
        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $to = $from->endOfMonth();

        return $this->dateRangeSession($from, $to);
    }

    /**
     * Convenience: pin the global range to a full calendar year.
     *
     * @return array<string, string>
     */
    protected function dateRangeYear(int $year): array {
        $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $to = $from->endOfYear();

        return $this->dateRangeSession($from, $to);
    }
}
