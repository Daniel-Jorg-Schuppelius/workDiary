<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelChargeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Travel;

use App\Enums\Tour\TourStatus;
use App\Models\{Customer, ForeignCustomer, Project, TimeEntry, Tour, TravelLog};
use App\Support\Setting;
use Illuminate\Support\{Carbon, Collection};

/**
 * Ermittelt die abrechenbaren Anfahrten zu einem Kunden im Abrechnungszeitraum.
 *
 * Eine Anfahrt entsteht, wenn an einem Tag eine Tour mit Stopp beim Kunden
 * stattfand. Modus (Pauschale/Kilometer), km-Quelle (Firmenstandort/Tour) und
 * Beträge kommen aus den globalen Org-Einstellungen ({@see Setting} Gruppe
 * `travel`), pro Kunde via `customers.travel_settings` überschreibbar.
 */
class TravelChargeService {
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'enabled' => false,
        'mode' => 'flat',           // 'flat' | 'km'
        'flat_amount' => 0.0,
        'rate_per_km' => 0.0,
        'km_source' => 'company',   // 'company' | 'tour'
        'round_trip' => true,
        'origin_lat' => null,
        'origin_lng' => null,
        'label' => 'Anfahrt',
    ];

    /**
     * Globale Anfahrt-Konfiguration, gemerged mit der Kunden-Übersteuerung
     * (`customers.travel_settings`). Nicht gesetzte/leere Kundenwerte erben.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(Customer $customer): array {
        $config = [];
        foreach (self::DEFAULTS as $key => $default) {
            $config[$key] = Setting::get("travel.$key", $default);
        }

        $override = (array) ($customer->travel_settings ?? []);
        foreach ($override as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $config[$key] = $value;
        }

        $config['enabled'] = (bool) $config['enabled'];
        $config['round_trip'] = (bool) $config['round_trip'];
        $config['flat_amount'] = (float) $config['flat_amount'];
        $config['rate_per_km'] = (float) $config['rate_per_km'];
        $config['origin_lat'] = $config['origin_lat'] !== null ? (float) $config['origin_lat'] : null;
        $config['origin_lng'] = $config['origin_lng'] !== null ? (float) $config['origin_lng'] : null;
        $config['mode'] = (string) $config['mode'];
        $config['km_source'] = (string) $config['km_source'];
        $config['label'] = (string) ($config['label'] ?: 'Anfahrt');

        return $config;
    }

    /**
     * Liefert die Anfahrt-Positionen für noch nicht abgerechnete Touren des
     * Kunden im Zeitraum.
     *
     * @param  array{from?: string|\Carbon\CarbonInterface|null, to?: string|\Carbon\CarbonInterface|null}  $range
     * @param  bool  $pureMaterialOnly  nur Touren an Tagen ohne abrechenbare Zeit
     *                                  (reine Materialtage) — reserviert die
     *                                  Anfahrt von Leistungstagen der Leistungsrechnung
     * @return Collection<int, TravelCharge>
     */
    public function chargesForRange(
        Customer $customer,
        ?Project $project,
        array $range,
        ?ForeignCustomer $foreignCustomer,
        bool $pureMaterialOnly,
    ): Collection {
        $config = $this->resolveConfig($customer);
        if (! $config['enabled']) {
            return new Collection;
        }

        $tours = Tour::query()
            ->where('travel_billed', false)
            ->where('status', '!=', TourStatus::Cancelled->value)
            ->when(! empty($range['from']), fn($q) => $q->whereDate('tour_date', '>=', Carbon::parse($range['from'])->toDateString()))
            ->when(! empty($range['to']), fn($q) => $q->whereDate('tour_date', '<=', Carbon::parse($range['to'])->toDateString()))
            ->whereHas('diaryEntries', function ($q) use ($customer, $project, $foreignCustomer): void {
                $q->where('customer_id', $customer->id);
                if ($project !== null) {
                    $q->where('project_id', $project->id);
                }
                if ($foreignCustomer !== null) {
                    $q->whereHas('project', fn($p) => $p->where('foreign_customer_id', $foreignCustomer->id));
                }
            })
            ->orderBy('tour_date')
            ->get();

        /** @var Collection<int, TravelCharge> $charges */
        $charges = new Collection;

        foreach ($tours as $tour) {
            $date = $tour->tour_date;
            if ($date === null) {
                continue;
            }

            if ($pureMaterialOnly && $this->hasBillableTime($customer, $project, $date)) {
                continue;
            }

            $charge = $this->chargeForTour($tour, $customer, $config);
            if ($charge !== null) {
                $charges->push($charge);
            }
        }

        return $charges;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function chargeForTour(Tour $tour, Customer $customer, array $config): ?TravelCharge {
        $date = $tour->tour_date;
        $dateLabel = $date->format('d.m.Y');

        if ($config['mode'] === 'flat') {
            $amount = (float) $config['flat_amount'];
            if ($amount <= 0) {
                return null;
            }

            return new TravelCharge(
                tour: $tour,
                date: $date,
                quantity: 1.0,
                unit: (string) __('Pauschale'),
                unitPrice: round($amount, 2),
                description: sprintf('%s %s', $config['label'], $dateLabel),
            );
        }

        // km-Modus
        $km = $config['km_source'] === 'tour'
            ? $this->tourKm($tour, $customer)
            : $this->companyKm($tour, $customer, $config);

        $km = round($km, 2);
        $rate = (float) $config['rate_per_km'];
        if ($km <= 0 || $rate <= 0) {
            return null;
        }

        return new TravelCharge(
            tour: $tour,
            date: $date,
            quantity: $km,
            unit: 'km',
            unitPrice: round($rate, 4),
            description: sprintf('%s %s km am %s', $config['label'], rtrim(rtrim(number_format($km, 2, ',', '.'), '0'), ','), $dateLabel),
        );
    }

    /**
     * Tatsächlich gefahrene km des Tages: Summe der TravelLogs (Kunde + Datum),
     * sonst die geplante Tour-Distanz. Wird unverändert übernommen (kein
     * Round-Trip-Faktor, da bereits real).
     */
    private function tourKm(Tour $tour, Customer $customer): float {
        $logged = (float) TravelLog::query()
            ->where('customer_id', $customer->id)
            ->whereDate('date', $tour->tour_date->toDateString())
            ->sum('distance_km');

        if ($logged > 0) {
            return $logged;
        }

        return (float) $tour->planned_distance_km;
    }

    /**
     * Luftlinie Firmenstandort → Kunde (×2 bei Round-Trip). Origin aus den
     * Einstellungen, Fallback auf den Tour-Start.
     *
     * @param  array<string, mixed>  $config
     */
    private function companyKm(Tour $tour, Customer $customer, array $config): float {
        $originLat = $config['origin_lat'] ?? ($tour->start_lat !== null ? (float) $tour->start_lat : null);
        $originLng = $config['origin_lng'] ?? ($tour->start_lng !== null ? (float) $tour->start_lng : null);
        $custLat = $customer->address_lat !== null ? (float) $customer->address_lat : null;
        $custLng = $customer->address_lng !== null ? (float) $customer->address_lng : null;

        if ($originLat === null || $originLng === null || $custLat === null || $custLng === null) {
            return 0.0;
        }

        $oneWay = $this->haversineKm($originLat, $originLng, $custLat, $custLng);

        return $config['round_trip'] ? $oneWay * 2 : $oneWay;
    }

    private function hasBillableTime(Customer $customer, ?Project $project, Carbon $date): bool {
        return TimeEntry::query()
            ->where('billable', true)
            ->whereDate('date', $date->toDateString())
            ->whereHas('project', function ($p) use ($customer, $project): void {
                $p->where('customer_id', $customer->id);
                if ($project !== null) {
                    $p->where('id', $project->id);
                }
            })
            ->exists();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}
