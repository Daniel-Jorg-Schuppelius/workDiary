<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MileageRateResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Travel;

use App\Models\TravelLog;

/**
 * Resolves the reimbursement rate (EUR/km) for a given vehicle type.
 * Reads defaults from config('timesheet.travel.rates'). Future versions may
 * layer per-organization overrides on top.
 */
class MileageRateResolver
{
    public function rateFor(string $vehicle, ?int $organizationId = null): float
    {
        $rates = (array) config('timesheet.travel.rates', []);

        return (float) ($rates[$vehicle] ?? 0.0);
    }

    public function rateForTravelLog(TravelLog $log): float
    {
        if ($log->rate_per_km !== null) {
            return (float) $log->rate_per_km;
        }

        return $this->rateFor((string) $log->vehicle, $log->organization_id);
    }
}
