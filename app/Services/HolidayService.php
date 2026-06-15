<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolidayService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Holiday as CustomHoliday;
use App\Support\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Yasumi\{Holiday, Yasumi};

class HolidayService {
    /**
     * Cache pro Provider-Key (Rechtsraum) und Jahr — damit ein Org-Wechsel
     * (anderer Yasumi-Provider) nicht versehentlich gecachte Feiertage eines
     * anderen Bundeslandes/Landes liefert.
     *
     * @var array<string, array<int, array<string, string>>>
     */
    private array $cache = [];

    /**
     * Aktiver Feiertags-Rechtsraum (Yasumi-Provider) der gebundenen
     * Organisation — settings['holidays']['provider'], sonst config-Default.
     * Dieselbe Quelle für Zuschläge (SurchargeCalculator) und Compliance.
     */
    public function provider(): string {
        return (string) Setting::get('holidays.provider', 'Germany');
    }

    public function locale(): string {
        return (string) Setting::get('holidays.locale', 'de_DE');
    }

    /**
     * Liefert eine Map [Y-m-d => Feiertagsname] für das gegebene Jahr.
     *
     * @return array<string, string>
     */
    public function forYear(int $year): array {
        $provider = $this->provider();
        $locale = $this->locale();

        if (isset($this->cache[$provider][$year])) {
            return $this->cache[$provider][$year];
        }

        try {
            $holidays = Yasumi::create($provider, $year, $locale);
        } catch (\Throwable) {
            return $this->cache[$provider][$year] = [];
        }

        $map = [];
        /** @var Holiday $h */
        foreach ($holidays->getHolidays() as $h) {
            $map[$h->format('Y-m-d')] = (string) $h->getName();
        }

        $custom = collect();
        if (Schema::hasTable('holidays')) {
            try {
                $custom = CustomHoliday::query()
                    ->where(function ($q) use ($year) {
                        $q->where('is_recurring', false)
                            ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"]);
                    })
                    ->orWhere('is_recurring', true)
                    ->get(['date', 'name', 'is_recurring']);
            } catch (\Throwable) {
                $custom = collect();
            }
        }

        foreach ($custom as $holiday) {
            /** @var CustomHoliday $holiday */
            $name = (string) $holiday->name;
            foreach ($holiday->resolveForYear($year) as $key) {
                // Benutzerdefinierte Feiertage haben Vorrang vor Provider-Namen.
                $map[$key] = $name;
            }
        }

        return $this->cache[$provider][$year] = $map;
    }

    public function nameFor(CarbonInterface $date): ?string {
        $map = $this->forYear((int) $date->year);

        return $map[$date->format('Y-m-d')] ?? null;
    }

    public function isHoliday(CarbonInterface $date): bool {
        return $this->nameFor($date) !== null;
    }
}
