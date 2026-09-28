<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivateUseComparison.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Enums\Travel\TripKind;
use App\Enums\Vehicle\VehiclePropulsion;
use App\Models\Asset\EnergyLog;
use App\Models\Fleet\{Vehicle, VehicleAnnualCost};
use App\Models\Travel\TravelLog;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\{CurrencyCode, RoundingMode};
use CommonToolkit\ValueObjects\{Decimal, Money};

/**
 * Jahresvergleich Fahrtenbuchmethode ↔ 1-%-Regel je Fahrzeug (MVP-993).
 *
 * Fahrtenbuch: Gesamtkosten (Energie aus den Tank-/Ladebelegen plus sonstige
 * Jahreskosten) × Anteil privater und Arbeitsweg-km an allen wirksamen km.
 * 1-%-Regel: Bruttolistenpreis auf volle 100 abgerundet, gemindert für E- und
 * Hybridfahrzeuge nach Anschaffungsdatum, 1 % je Nutzungsmonat plus 0,03 % je
 * Entfernungs-km und Monat.
 * Nutzungsmonate sind die Monate mit Fahrten. Keine Steuerberatung.
 */
final class PrivateUseComparison {
    /** Listenpreisgrenze für 0,25 % bei reinen E-Fahrzeugen nach Anschaffung ab (§ 6 Abs. 1 Nr. 4 EStG). */
    private const ELECTRIC_LIMITS = ['2019-01-01' => '40000', '2020-01-01' => '60000', '2024-01-01' => '70000', '2025-07-01' => '100000'];

    /**
     * @return array{currency: CurrencyCode, months: int, km_total: int, km_private: int, km_commute: int, energy: Money, other: Money, total_costs: Money, logbook: Money, factor: string|null, one_percent: Money|null, cheaper: string|null}
     */
    public function compare(Vehicle $vehicle, int $year): array {
        $currency = $vehicle->currency ?? CurrencyCode::tryFrom(strtoupper((string) config('invoicing.default_currency', 'EUR'))) ?? CurrencyCode::Euro;
        $from = CarbonImmutable::parse(sprintf('%04d-01-01', $year));
        $to = $from->endOfYear()->startOfDay();

        $trips = TravelLog::query()->where('vehicle_id', $vehicle->id)->effective()
            ->whereNotNull('odometer_start_km')->whereNotNull('odometer_end_km')
            ->whereBetween('date', DateRange::days($from, $to))
            ->get(['id', 'date', 'trip_kind', 'odometer_start_km', 'odometer_end_km']);
        $km = ['total' => 0, TripKind::Private_->value => 0, TripKind::Commute->value => 0];
        $months = [];
        foreach ($trips as $trip) {
            $driven = max(0, (int) $trip->odometer_end_km - (int) $trip->odometer_start_km);
            $km['total'] += $driven;
            if (isset($km[$trip->trip_kind->value])) {
                $km[$trip->trip_kind->value] += $driven;
            }
            $months[$trip->date?->format('Y-m') ?? ''] = true;
        }
        $monthCount = count(array_filter(array_keys($months)));

        $energy = Money::sum(
            EnergyLog::query()->where('vehicle_id', $vehicle->id)->whereNotNull('cost_total')
                ->whereBetween('started_at', DateRange::days($from, $to))->pluck('cost_total')
                ->map(static fn (mixed $cost): Money => Money::of((string) $cost, $currency)),
            $currency,
        );
        $annual = VehicleAnnualCost::query()->where('vehicle_id', $vehicle->id)->where('year', $year)->first();
        $other = $annual instanceof VehicleAnnualCost ? $annual->cost_amount : Money::zero($currency);
        $total = $energy->plus($other);
        $privateKm = $km[TripKind::Private_->value] + $km[TripKind::Commute->value];
        $logbook = $km['total'] > 0
            ? $total->times(Decimal::of($privateKm)->dividedBy(Decimal::of($km['total']), 10)->getValue())
            : Money::zero($currency);

        [$factor, $onePercent] = $this->onePercent($vehicle, $year, $monthCount, $currency);

        return [
            'currency' => $currency,
            'months' => $monthCount,
            'km_total' => $km['total'],
            'km_private' => $km[TripKind::Private_->value],
            'km_commute' => $km[TripKind::Commute->value],
            'energy' => $energy,
            'other' => $other,
            'total_costs' => $total,
            'logbook' => $logbook,
            'factor' => $factor,
            'one_percent' => $onePercent,
            'cheaper' => $onePercent === null ? null : ($logbook->lessThanOrEqual($onePercent) ? 'logbook' : 'one_percent'),
        ];
    }

    /** @return array{0: string|null, 1: Money|null} Minderungsfaktor und Wert nach der 1-%-Regel */
    private function onePercent(Vehicle $vehicle, int $year, int $months, CurrencyCode $currency): array {
        $listPrice = $vehicle->list_price_amount;
        if ($listPrice === null || ! $listPrice->isPositive() || $listPrice->getCurrency() !== $currency) {
            return [null, null];
        }
        $rounded = Decimal::of($listPrice->getAmount())->dividedBy(Decimal::of(100), 0, RoundingMode::Truncate)->times(Decimal::of(100));
        $factor = $this->factor($vehicle, $rounded, $year);
        $base = Money::of($rounded->getValue(), $currency)->times($factor);
        $value = $base->percentage('1')->times($months)
            ->plus($base->percentage('0.03')->times(((int) $vehicle->commute_distance_km) * $months));

        return [$factor, $value];
    }

    /**
     * Minderung nach Anschaffungsdatum (ohne Datum: Jahresbeginn der Auswertung):
     * erst für Anschaffungen ab 2019; begünstigter Plug-in-Hybrid 0,5 %, reines
     * E-Fahrzeug 0,25 % bis zur Listenpreisgrenze, darüber 0,5 %.
     *
     * @return numeric-string
     */
    private function factor(Vehicle $vehicle, Decimal $listPrice, int $year): string {
        $acquired = $vehicle->acquired_on?->toDateString() ?? sprintf('%04d-01-01', $year);
        if ($acquired < array_key_first(self::ELECTRIC_LIMITS)) {
            return '1';
        }
        if ($vehicle->propulsion === VehiclePropulsion::Hybrid) {
            return $vehicle->is_externally_chargeable ? '0.5' : '1';
        }
        if ($vehicle->propulsion !== VehiclePropulsion::Electric) {
            return '1';
        }
        $limit = '0';
        foreach (self::ELECTRIC_LIMITS as $from => $value) {
            if ($acquired >= $from) {
                $limit = $value;
            }
        }

        return $listPrice->lessThanOrEqual(Decimal::of($limit)) ? '0.25' : '0.5';
    }
}
