<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Enums\Expense\PerDiemDayKind;
use App\Models\{PerDiemDay, PerDiemTrip};
use Carbon\{CarbonImmutable, CarbonInterface, CarbonPeriod};
use RuntimeException;

/**
 * Berechnung der Verpflegungsmehraufwendungen (DE BMF).
 *
 *  - Mehrtagesreise: Anreisetag = partial, volle Zwischentage = full, Abreisetag = partial.
 *  - Eintagesreise: nur wenn Abwesenheit > 8 h (DE) → partial.
 *  - Mahlzeitenkürzung vom DE-Volltagessatz: Frühstück 20 %, Mittag/Abend je 40 %.
 *  - Übernachtungspauschale (overnight) wird hier NICHT additiv hinzugefügt;
 *    sie wird optional in einer separaten Position oder auf dem Trip aggregiert.
 */
class PerDiemCalculator {
    public function __construct(private readonly PerDiemRateLookup $rates) {}

    /**
     * Erzeugt PerDiemDay-Einträge (unsaved) für einen Trip anhand seines Zeitraums.
     *
     *  - Berücksichtigt accommodation_provided NICHT für Mahlzeiten (separat zu pflegen).
     *  - Erkennt Eintages- vs. Mehrtagesreise anhand started_at/ended_at.
     *  - Wirft RuntimeException, wenn für einen Tag kein passender Satz hinterlegt ist.
     *
     * @return list<PerDiemDay>
     */
    public function buildDays(PerDiemTrip $trip): array {
        // Reisetage sind LOKALE Kalendertage: die UTC-gespeicherten Zeitpunkte
        // erst in die Anzeige-Zeitzone umrechnen, sonst wird eine Eintagesreise
        // 00:30–09:30 lokal (UTC: über Mitternacht) zur Mehrtagesreise mit
        // zwei Teiltagessätzen — steuerlich überhöht (Whitebox 2026-07-10, Z2).
        $tz = \App\Support\Tz::current();
        $start = CarbonImmutable::parse($trip->started_at)->setTimezone($tz);
        $end = CarbonImmutable::parse($trip->ended_at)->setTimezone($tz);

        if ($end->lessThan($start)) {
            throw new RuntimeException('Ende der Reise liegt vor dem Beginn.');
        }

        $startDate = $start->startOfDay();
        $endDate = $end->startOfDay();
        $isSingleDay = $startDate->equalTo($endDate);

        if ($isSingleDay) {
            $hours = $start->diffInMinutes($end) / 60;
            if ($hours <= 8.0) {
                return [];
            }
            $day = $this->buildSingleDay($trip, $start);

            return [$day];
        }

        $days = [];
        /** @var CarbonInterface $cursor */
        foreach (CarbonPeriod::create($startDate, $endDate) as $cursor) {
            $date = CarbonImmutable::parse($cursor);
            $kind = match (true) {
                $date->equalTo($startDate) => PerDiemDayKind::DepartureDay,
                $date->equalTo($endDate) => PerDiemDayKind::ReturnDay,
                default => PerDiemDayKind::FullDay,
            };
            $days[] = $this->buildDay($trip, $date, $kind);
        }

        return $days;
    }

    /** Berechnet alle Beträge einer einzelnen Day-Zeile neu (in-place). */
    public function recalculateDay(PerDiemDay $day): void {
        $rate = $day->rate ?? $this->rates->forOrFail(
            $day->country ?: 'DE',
            CarbonImmutable::parse($day->date),
        );
        $day->per_diem_rate_id = $rate->id;
        $day->country = $rate->country;
        $day->currency = $rate->currency;

        $base = $day->kind->usesFullDayAmount()
            ? (float) $rate->full_day_amount
            : (float) $rate->partial_day_amount;

        $fullDay = (float) $rate->full_day_amount;
        $deductB = $day->meal_breakfast ? round($fullDay * 0.20, 2) : 0.0;
        $deductL = $day->meal_lunch ? round($fullDay * 0.40, 2) : 0.0;
        $deductD = $day->meal_dinner ? round($fullDay * 0.40, 2) : 0.0;
        $deductTotal = round($deductB + $deductL + $deductD, 2);

        $amount = round(max(0.0, $base - $deductTotal), 2);

        $day->base_amount = number_format($base, 2, '.', '');
        $day->deduction_breakfast = number_format($deductB, 2, '.', '');
        $day->deduction_lunch = number_format($deductL, 2, '.', '');
        $day->deduction_dinner = number_format($deductD, 2, '.', '');
        $day->deductions_total = number_format($deductTotal, 2, '.', '');
        $day->amount = number_format($amount, 2, '.', '');
    }

    private function buildSingleDay(PerDiemTrip $trip, CarbonImmutable $start): PerDiemDay {
        return $this->buildDay($trip, $start->startOfDay(), PerDiemDayKind::SingleDay);
    }

    private function buildDay(PerDiemTrip $trip, CarbonImmutable $date, PerDiemDayKind $kind): PerDiemDay {
        $day = new PerDiemDay([
            'per_diem_trip_id' => $trip->id,
            'date' => $date->toDateString(),
            'kind' => $kind,
            'country' => $trip->country ?: 'DE',
            'currency' => 'EUR',
            'meal_breakfast' => false,
            'meal_lunch' => false,
            'meal_dinner' => false,
        ]);
        $day->setRelation('trip', $trip);

        $this->recalculateDay($day);

        return $day;
    }
}
