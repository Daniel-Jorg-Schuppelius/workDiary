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
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\TimeEntry;
use App\Services\HolidayService;
use Carbon\CarbonInterface;

/**
 * Löst den Sonderkonditions-Satz (Feature 098) für einen Zeiteintrag auf:
 * Tagtyp über workdays_per_week des Agreements (6 ⇒ nur So, 5 ⇒ Sa+So,
 * optional Feiertag = Wochenende),
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

    /**
     * Pauschale Anfahrtsminuten, die dieser Eintrag zusätzlich abrechnet
     * (Feature 098). 0 = keine — die Kondition führt keine Anfahrt, der Eintrag
     * ist keine Arbeitszeit (Fahrt/Bereitschaft tragen keine eigene Anfahrt),
     * er rechnet zum Festpreis, oder seine Tätigkeit steht nicht auf der Liste.
     */
    public function travelMinutesFor(TimeEntry $entry): int {
        $agreement = $this->agreementFor($entry->project?->customer_id);
        if ($agreement === null || $agreement->travel_minutes_per_entry <= 0) {
            return 0;
        }

        if ($entry->kind !== TimeEntryKind::Work || $entry->fixed_rate !== null) {
            return 0;
        }

        $categories = array_map('intval', $agreement->travel_categories ?? []);
        if ($categories !== [] && ! in_array((int) $entry->activity_category_id, $categories, true)) {
            return 0;
        }

        return $agreement->travel_minutes_per_entry;
    }

    /**
     * Wochenende im Sinne der Kondition: alles jenseits der vereinbarten
     * Arbeitstage (6 ⇒ nur So) und — sofern eingeschaltet — Feiertage. Quelle
     * ist derselbe {@see HolidayService} wie für Zuschläge und Compliance
     * (Rechtsraum der Organisation + eigene Feiertage), pro Jahr gecacht.
     */
    public function isWeekend(CustomerBillingAgreement $agreement, CarbonInterface $date): bool {
        if ($date->isoWeekday() > $agreement->workdays_per_week) {
            return true;
        }

        return $agreement->holidays_as_weekend && app(HolidayService::class)->isHoliday($date);
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
