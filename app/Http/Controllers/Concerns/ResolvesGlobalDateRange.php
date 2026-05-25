<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesGlobalDateRange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;

/**
 * Resolves date hints for report controllers: query parameters take
 * precedence (so bookmarked URLs still work), otherwise the globally
 * selected DateRangeContext (from the header widget) is used.
 */
trait ResolvesGlobalDateRange {
    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, preset: string, label: string}
     */
    protected function globalDateRange(): array {
        return app(DateRangeContext::class)->current();
    }

    /**
     * Splits a date range into a list of months (Y-m keys) for tab navigation.
     *
     * @return list<array{key:string,year:int,month:int,label:string,shortLabel:string}>
     */
    protected function buildMonthsInRange(CarbonImmutable $from, CarbonImmutable $to): array {
        $months = [];
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'year' => $cursor->year,
                'month' => $cursor->month,
                'label' => $cursor->translatedFormat('F Y'),
                'shortLabel' => $cursor->translatedFormat('M Y'),
            ];
            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}
