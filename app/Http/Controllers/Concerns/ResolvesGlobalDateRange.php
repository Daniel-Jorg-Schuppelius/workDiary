<?php

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
}
