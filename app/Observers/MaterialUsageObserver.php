<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
