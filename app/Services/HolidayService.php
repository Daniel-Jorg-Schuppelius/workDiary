<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Yasumi\Holiday;
use Yasumi\Yasumi;

class HolidayService {
    /**
     * @var array<int, array<string, string>>
     */
    private array $cache = [];

    /**
     * Liefert eine Map [Y-m-d => Feiertagsname] für das gegebene Jahr.
     *
     * @return array<string, string>
     */
    public function forYear(int $year): array {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $provider = (string) config('app.holidays.provider', 'Germany');
        $locale = (string) config('app.holidays.locale', 'de_DE');

        try {
            $holidays = Yasumi::create($provider, $year, $locale);
        } catch (\Throwable) {
            return $this->cache[$year] = [];
        }

        $map = [];
        /** @var Holiday $h */
        foreach ($holidays->getHolidays() as $h) {
            $map[$h->format('Y-m-d')] = (string) $h->getName();
        }

        return $this->cache[$year] = $map;
    }

    public function nameFor(CarbonInterface $date): ?string {
        $map = $this->forYear((int) $date->year);

        return $map[$date->format('Y-m-d')] ?? null;
    }

    public function isHoliday(CarbonInterface $date): bool {
        return $this->nameFor($date) !== null;
    }
}
