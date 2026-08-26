<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DepreciationCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FixedAsset;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\ValueObjects\Money;

/**
 * Lineare AfA (Feature 133, MVP-698) — rein, ohne Datenbank.
 *
 * Regeln:
 *  - Bemessungsgrundlage = AK/HK − Restwert, gleichmäßig über die
 *    Nutzungsdauer in Monaten (§ 7 Abs. 1 S. 1 EStG).
 *  - Anschaffungs- und Abgangsjahr zeitanteilig monatsgenau; der Monat der
 *    Anschaffung bzw. des Abgangs zählt voll (§ 7 Abs. 1 S. 4 EStG).
 *  - Jede Jahreszeile wird kaufmännisch auf Cent gerundet; das letzte Jahr
 *    nimmt die Restdifferenz, damit die Summe exakt AK − RW ergibt und der
 *    Restbuchwert genau auf dem Restwert endet.
 *  - Ein Abgang beendet den Plan im Abgangsjahr; der verbleibende Buchwert
 *    bleibt stehen (die Abgangsbuchung selbst ist nicht Teil des MVP).
 *
 * Geschäftsjahre werden aus dem Startmonat abgeleitet (Kalenderjahr = 1);
 * der Schlüssel einer Zeile ist das Startjahr des Geschäftsjahres.
 */
final class DepreciationCalculator {
    /** @return list<DepreciationScheduleRow> */
    public function scheduleFor(FixedAsset $asset, int $fiscalYearStartMonth = 1): array {
        $currency = $asset->currency;
        $cost = $asset->acquisition_cost ?? Money::zero($currency);
        $base = $asset->depreciableBase();
        $totalMonths = max(0, $asset->useful_life_months);

        if ($base->isZero() || $totalMonths === 0) {
            return [];
        }

        $acquired = $asset->acquiredOn();
        $disposed = $asset->disposedOn();
        $startMonth = min(12, max(1, $fiscalYearStartMonth));

        $yearStart = $this->fiscalYearStartFor($acquired, $startMonth);
        $remainingMonths = $totalMonths;
        $allocated = Money::zero($currency);
        $rows = [];

        while ($remainingMonths > 0) {
            $yearEnd = $yearStart->addYear()->subDay();

            // Monate dieses Geschäftsjahres, in denen die Anlage im Bestand ist —
            // ab Anschaffungsmonat, bis Abgangsmonat (jeweils einschließlich).
            $from = $acquired->greaterThan($yearStart) ? $acquired : $yearStart;
            $to = $yearEnd;
            $endsWithDisposal = false;
            if ($disposed instanceof CarbonImmutable && $disposed->lessThanOrEqualTo($yearEnd)) {
                if ($disposed->lessThan($from)) {
                    break;
                }
                $to = $disposed;
                $endsWithDisposal = true;
            }

            $months = min($remainingMonths, $this->monthsInclusive($from, $to));
            if ($months <= 0) {
                break;
            }

            $isLast = ! $endsWithDisposal && $months === $remainingMonths;
            $amount = $isLast
                ? $base->minus($allocated)
                : $base->times($months)->dividedBy($totalMonths, RoundingMode::HalfUp);

            // Rundung darf die Bemessungsgrundlage nie überschreiten.
            $open = $base->minus($allocated);
            if ($amount->greaterThan($open)) {
                $amount = $open;
            }
            if ($amount->isNegative()) {
                $amount = Money::zero($currency);
            }

            $allocated = $allocated->plus($amount);
            $rows[] = new DepreciationScheduleRow(
                fiscalYear: $yearStart->year,
                label: $this->label($yearStart, $yearEnd),
                startsOn: $yearStart,
                endsOn: $yearEnd,
                months: $months,
                amount: $amount,
                bookValueEnd: $cost->minus($allocated),
            );

            if ($endsWithDisposal) {
                break;
            }

            $remainingMonths -= $months;
            $yearStart = $yearStart->addYear();
        }

        return $rows;
    }

    /** AfA-Betrag eines Geschäftsjahres (Startjahr) oder null. */
    public function amountForYear(FixedAsset $asset, int $fiscalYearStartYear, int $fiscalYearStartMonth = 1): Money {
        foreach ($this->scheduleFor($asset, $fiscalYearStartMonth) as $row) {
            if ($row->fiscalYear === $fiscalYearStartYear) {
                return $row->amount;
            }
        }

        return Money::zero($asset->currency);
    }

    /** Zeile eines Geschäftsjahres oder null, wenn die Anlage dort keine AfA hat. */
    public function rowForYear(FixedAsset $asset, int $fiscalYearStartYear, int $fiscalYearStartMonth = 1): ?DepreciationScheduleRow {
        foreach ($this->scheduleFor($asset, $fiscalYearStartMonth) as $row) {
            if ($row->fiscalYear === $fiscalYearStartYear) {
                return $row;
            }
        }

        return null;
    }

    /** Erster Tag des Geschäftsjahres, das `$date` enthält. */
    public function fiscalYearStartFor(CarbonImmutable $date, int $fiscalYearStartMonth = 1): CarbonImmutable {
        $startMonth = min(12, max(1, $fiscalYearStartMonth));
        $year = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $startMonth))->startOfDay();
    }

    /** Kalendermonate zwischen zwei Daten, beide Monate einschließlich. */
    private function monthsInclusive(CarbonImmutable $from, CarbonImmutable $to): int {
        if ($to->lessThan($from)) {
            return 0;
        }

        return ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month) + 1;
    }

    private function label(CarbonImmutable $start, CarbonImmutable $end): string {
        return $start->year === $end->year ? (string) $start->year : $start->year . '/' . $end->year;
    }
}
