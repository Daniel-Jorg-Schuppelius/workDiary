<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Models\PerDiemRate;
use Carbon\CarbonImmutable;
use RuntimeException;

class PerDiemRateLookup {
    /**
     * Liefert den gültigen Pauschalsatz für Land + Datum oder null.
     */
    public function for(string $country, CarbonImmutable $date): ?PerDiemRate {
        return PerDiemRate::query()
            ->forCountry($country)
            ->activeOn($date->toMutable())
            ->orderByDesc('valid_from')
            ->first();
    }

    public function forOrFail(string $country, CarbonImmutable $date): PerDiemRate {
        $rate = $this->for($country, $date);
        if ($rate === null) {
            throw new RuntimeException(sprintf(
                'Kein Per-Diem-Satz für Land %s am %s hinterlegt.',
                $country,
                $date->toDateString()
            ));
        }

        return $rate;
    }
}
