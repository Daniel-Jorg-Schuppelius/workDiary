<?php

namespace App\Observers;

use App\Models\MaterialUsage;

class MaterialUsageObserver
{
    public function saved(MaterialUsage $usage): void
    {
        $usage->timesheet?->recalcTotals();
    }

    public function deleted(MaterialUsage $usage): void
    {
        $usage->timesheet?->recalcTotals();
    }
}
