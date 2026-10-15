<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZoneFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location;

use App\Models\Customer\Customer;
use App\Models\Diary\Tour;
use App\Models\Facility\Site;
use App\Models\Location\CustomerGeofence;
use App\Models\Platform\User;
use CommonToolkit\Helper\Geo\GeoHelper;

/**
 * Zonen aus Adressen (MVP-1181): Kunde, Standort oder die Stopps einer Tour
 * (temporär, nur am Tourtag). Legt nie doppelt an: eine aktive Zone, deren
 * Mittelpunkt im Radius liegt, zählt als vorhanden.
 */
class ZoneFactory {
    public function forCustomer(Customer $customer, ?User $actor = null): ?CustomerGeofence {
        if ($customer->address_lat === null || $customer->address_lng === null) {
            return null;
        }

        return $this->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'label' => (string) $customer->name,
            'center_lat' => (float) $customer->address_lat,
            'center_lng' => (float) $customer->address_lng,
        ], $actor);
    }

    public function forSite(Site $site, ?User $actor = null): ?CustomerGeofence {
        if ($site->geo_lat === null || $site->geo_lng === null || ! $site->customer_id) {
            return null;
        }

        return $this->create([
            'organization_id' => $site->organization_id,
            'customer_id' => $site->customer_id,
            'site_id' => $site->id,
            'label' => (string) $site->name,
            'center_lat' => (float) $site->geo_lat,
            'center_lng' => (float) $site->geo_lng,
        ], $actor);
    }

    /**
     * Temporäre Zonen je Stopp einer Tour (nur am Tourtag gültig). Stopps, die
     * schon eine aktive Dauerzone des Kunden am selben Ort haben, brauchen keine.
     *
     * @return list<CustomerGeofence>
     */
    public function forTour(Tour $tour): array {
        $zones = [];
        foreach ($tour->orderedStops()->with('customer:id,address_lat,address_lng')->get() as $stop) {
            $point = $stop->planningCoordinates();
            if ($point === null) {
                continue;
            }
            $zone = $this->create([
                'organization_id' => $tour->organization_id,
                'customer_id' => $stop->customer_id,
                'project_id' => $stop->project_id,
                'tour_id' => $tour->id,
                'diary_entry_id' => $stop->id,
                'valid_on' => $tour->tour_date?->toDateString(),
                'label' => (string) $stop->title,
                'center_lat' => $point['lat'],
                'center_lng' => $point['lng'],
            ], null);
            if ($zone !== null) {
                $zones[] = $zone;
            }
        }

        return $zones;
    }

    /**
     * Alle Kunden mit Koordinaten ohne aktive Dauerzone am Ort bekommen eine.
     *
     * @return int angelegte Zonen
     */
    public function forAllCustomers(?User $actor = null): int {
        $count = 0;
        Customer::query()->whereNotNull('address_lat')->whereNotNull('address_lng')->orderBy('id')
            ->each(function (Customer $customer) use (&$count, $actor): void {
                if ($this->forCustomer($customer, $actor) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(array $attributes, ?User $actor): ?CustomerGeofence {
        $defaults = (array) config('location.defaults', []);
        $radius = (int) ($defaults['radius_m'] ?? 100);
        $lat = (float) $attributes['center_lat'];
        $lng = (float) $attributes['center_lng'];

        $existing = CustomerGeofence::query()->active()
            ->when(isset($attributes['tour_id']), fn($q) => $q->where('tour_id', $attributes['tour_id'])->where('diary_entry_id', $attributes['diary_entry_id']))
            ->when(! isset($attributes['tour_id']), fn($q) => $q->permanent()->where('customer_id', $attributes['customer_id'] ?? null))
            ->get()
            ->first(static fn(CustomerGeofence $zone): bool => GeoHelper::haversineMeters($lat, $lng, (float) $zone->center_lat, (float) $zone->center_lng) <= (int) $zone->radius_m);
        if ($existing !== null) {
            return null;
        }
        // Ein Stopp in einer bestehenden Dauerzone des Kunden braucht keine Tourzone.
        if (isset($attributes['tour_id']) && ($attributes['customer_id'] ?? null) !== null) {
            $covered = CustomerGeofence::query()->active()->permanent()->where('customer_id', $attributes['customer_id'])->get()
                ->first(static fn(CustomerGeofence $zone): bool => GeoHelper::haversineMeters($lat, $lng, (float) $zone->center_lat, (float) $zone->center_lng) <= (int) $zone->radius_m);
            if ($covered !== null) {
                return null;
            }
        }

        return CustomerGeofence::create($attributes + [
            'radius_m' => $radius,
            'min_dwell_minutes' => (int) ($defaults['min_dwell_minutes'] ?? 5),
            'gap_merge_minutes' => (int) ($defaults['gap_merge_minutes'] ?? 10),
            'is_active' => true,
            'created_by' => $actor?->id,
        ]);
    }
}
