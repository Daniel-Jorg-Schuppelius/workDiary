<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Weather;

use App\Models\{Customer, DiaryEntry, Organization, Project, Protocol, Site, User, WeatherSnapshot};
use App\Services\Weather\Contracts\WeatherProvider;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Wetter-Snapshots je Ort und Tag (Feature 062, MVP-131). Idempotent: ein
 * bereits vorhandener Snapshot (Org, Koordinate, Tag, Provider) wird
 * unverändert zurückgegeben, nie neu abgerufen. Provider-Ausfall führt zu
 * `null` (kein Snapshot, kein Fehler) — der Tag bleibt „nachholbar". Auch für
 * vergangene Tage nutzbar (nachträglicher Abruf).
 */
class WeatherService {
    public function __construct(private readonly WeatherProvider $provider) {}

    public function snapshot(Organization $organization, float $lat, float $lng, CarbonInterface $date, ?User $actor = null): ?WeatherSnapshot {
        $lat = round($lat, 6);
        $lng = round($lng, 6);

        $existing = WeatherSnapshot::query()
            ->where('organization_id', $organization->id)
            ->where('geo_lat', $lat)
            ->where('geo_lng', $lng)
            ->whereDate('snapshot_date', $date->toDateString())
            ->where('provider', $this->provider->key())
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $reading = $this->provider->daily($lat, $lng, $date);
        if ($reading === null) {
            return null; // Ausfall: blockiert nichts, bleibt nachholbar.
        }

        return WeatherSnapshot::query()->create([
            'organization_id' => $organization->id,
            'geo_lat' => $lat,
            'geo_lng' => $lng,
            'snapshot_date' => $date->toDateString(),
            'provider' => $this->provider->key(),
            'fetched_at' => Carbon::now(),
            'temp_min' => $reading['temp_min'],
            'temp_max' => $reading['temp_max'],
            'precipitation_mm' => $reading['precipitation_mm'],
            'wind_gust_kmh' => $reading['wind_gust_kmh'],
            'weather_code' => $reading['weather_code'],
            'raw' => $reading['raw'],
            'created_by' => $actor?->id,
        ]);
    }

    /** Bequemabruf für einen Einsatzort mit hinterlegten Koordinaten. */
    public function snapshotForSite(Site $site, CarbonInterface $date, ?User $actor = null): ?WeatherSnapshot {
        if ($site->geo_lat === null || $site->geo_lng === null) {
            return null;
        }
        $organization = $site->organization;
        if (! $organization instanceof Organization) {
            return null;
        }

        return $this->snapshot($organization, (float) $site->geo_lat, (float) $site->geo_lng, $date, $actor);
    }

    /**
     * Hängt den Wetter-Messwert an einen Tagesbericht/Bautagebuch-Protokoll.
     * Der Tag ist `occurred_at`; die Koordinaten kommen aus dem Subjekt (Site
     * direkt, sonst über den zugeordneten Kunden von Customer/Project/
     * DiaryEntry). Bereits verknüpft → unverändert (Snapshot ist immutable).
     */
    public function snapshotForProtocol(Protocol $protocol, ?User $actor = null): ?WeatherSnapshot {
        if ($protocol->weather_snapshot_id !== null) {
            return $protocol->weatherSnapshot;
        }

        $coords = $this->coordsForSubject($protocol->subject);
        $organization = $protocol->organization;
        if ($coords === null || ! $organization instanceof Organization) {
            return null;
        }

        $snapshot = $this->snapshot($organization, $coords[0], $coords[1], $protocol->occurred_at ?? Carbon::now(), $actor);
        if ($snapshot !== null) {
            $protocol->forceFill(['weather_snapshot_id' => $snapshot->id])->save();
        }

        return $snapshot;
    }

    /** @return array{0: float, 1: float}|null Koordinaten des Protokoll-Subjekts. */
    private function coordsForSubject(?Model $subject): ?array {
        if ($subject instanceof Site && $subject->geo_lat !== null && $subject->geo_lng !== null) {
            return [(float) $subject->geo_lat, (float) $subject->geo_lng];
        }
        if ($subject instanceof Customer) {
            return $this->customerCoords($subject);
        }
        if ($subject instanceof Project && $subject->customer instanceof Customer) {
            return $this->customerCoords($subject->customer);
        }
        if ($subject instanceof DiaryEntry && $subject->customer instanceof Customer) {
            return $this->customerCoords($subject->customer);
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null */
    private function customerCoords(Customer $customer): ?array {
        return $customer->address_lat !== null && $customer->address_lng !== null
            ? [(float) $customer->address_lat, (float) $customer->address_lng]
            : null;
    }
}
