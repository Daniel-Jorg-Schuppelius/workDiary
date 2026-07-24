<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementRateResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\BillingRateDayType;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\TimeEntry;
use Carbon\CarbonInterface;

/**
 * Löst den Sonderkonditions-Satz (Feature 098) für einen Zeiteintrag auf:
 * Tagtyp über workdays_per_week des Agreements (6 ⇒ nur So, 5 ⇒ Sa+So),
 * Kategorie-Satz vor Kategorie-Fallback (activity_category_id=NULL), fehlt der
 * Wochenendsatz greift der Werktagssatz. Wird vom {@see \App\Services\RateCalculator}
 * VOR dem User-Satz befragt.
 *
 * Als `scoped` gebunden (AppServiceProvider): der Cache lebt pro Request bzw.
 * pro Queue-Job und wird zwischen Jobs verworfen — ein langlebiger Worker
 * rechnet so nie mit veralteten Konditionen. Innerhalb desselben Request/Jobs
 * invalidieren die saved/deleted-Hooks von Agreement/Rate den Cache via
 * {@see self::flush()}.
 */
class AgreementRateResolver {
    /** @var array<int, CustomerBillingAgreement|null> Request-/Job-Cache je customer_id (Rates eager geladen). */
    private array $cache = [];

    public function flush(): void {
        $this->cache = [];
    }

    public function agreementFor(?int $customerId): ?CustomerBillingAgreement {
        if ($customerId === null) {
            return null;
        }

        if (! array_key_exists($customerId, $this->cache)) {
            $this->cache[$customerId] = CustomerBillingAgreement::query()
                ->where('customer_id', $customerId)
                ->where('active', true)
                ->with('rates')
                ->first();
        }

        return $this->cache[$customerId];
    }

    public function rateFor(TimeEntry $entry): ?CustomerBillingRate {
        $agreement = $this->agreementFor($entry->project?->customer_id);
        if ($agreement === null || $entry->date === null) {
            return null;
        }

        $date = $entry->date;
        $dayType = $this->isWeekend($agreement, $date) ? BillingRateDayType::Weekend : BillingRateDayType::Weekday;

        return $this->pick($agreement, $entry->activity_category_id, $dayType, $date)
            ?? ($dayType === BillingRateDayType::Weekend
                ? $this->pick($agreement, $entry->activity_category_id, BillingRateDayType::Weekday, $date)
                : null);
    }

    public function isWeekend(CustomerBillingAgreement $agreement, CarbonInterface $date): bool {
        return $date->isoWeekday() > $agreement->workdays_per_week;
    }

    private function pick(
        CustomerBillingAgreement $agreement,
        ?int $activityCategoryId,
        BillingRateDayType $dayType,
        CarbonInterface $date
    ): ?CustomerBillingRate {
        $candidates = $agreement->rates
            ->filter(fn (CustomerBillingRate $rate): bool => $rate->day_type === $dayType
                && ($rate->valid_from === null || $rate->valid_from->lte($date))
                && ($rate->valid_until === null || $rate->valid_until->gte($date)))
            ->sortByDesc(fn (CustomerBillingRate $rate): int => $rate->valid_from?->getTimestamp() ?? 0);

        if ($activityCategoryId !== null) {
            $match = $candidates->firstWhere('activity_category_id', $activityCategoryId);
            if ($match !== null) {
                return $match;
            }
        }

        return $candidates->firstWhere('activity_category_id', null);
    }
}
