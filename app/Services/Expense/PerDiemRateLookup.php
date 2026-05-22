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
     *
     * Wenn `$region` angegeben ist, wird zuerst nach einer exakten Region-Übereinstimmung
     * gesucht; existiert keine, fällt die Suche auf den Standard-Tarif des Landes
     * (region_label IS NULL) zurück.
     */
    public function for(string $country, CarbonImmutable $date, ?string $region = null): ?PerDiemRate {
        return PerDiemRate::query()
            ->forCountry($country)
            ->forRegion($region)
            ->activeOn($date->toMutable())
            ->orderByRaw('CASE WHEN region_label IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('valid_from')
            ->first();
    }

    public function forOrFail(string $country, CarbonImmutable $date, ?string $region = null): PerDiemRate {
        $rate = $this->for($country, $date, $region);
        if ($rate === null) {
            throw new RuntimeException(sprintf(
                'Kein Per-Diem-Satz für Land %s%s am %s hinterlegt.',
                $country,
                $region !== null ? ' (Region '.$region.')' : '',
                $date->toDateString()
            ));
        }

        return $rate;
    }
}
