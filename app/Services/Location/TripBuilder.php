<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TripBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location;

use App\Enums\Location\{LocationPendingTripStatus, LocationVisitStatus};
use App\Models\Location\{LocationPendingTrip, LocationPoint, LocationVisit};
use App\Models\Scopes\OrganizationScope;
use App\Services\Location\Contracts\TrackDistanceSource;
use CommonToolkit\Helper\Geo\GeoHelper;

/**
 * Fahrtvorschlag zwischen zwei Besuchen (MVP-1185): die Punkte zwischen dem
 * Verlassen der vorigen und dem Betreten der nächsten Zone ergeben eine
 * Fahrt — Strecke per Kartenabgleich, sonst Spurlänge, gekennzeichnet. Nur
 * ein Vorschlag; gebucht wird im Prüf-Eingang mit Fahrzeug und Zweck.
 */
class TripBuilder {
    /** Längste Lücke zwischen zwei Besuchen, die noch als eine Fahrt gilt. */
    public const MAX_GAP_HOURS = 12;

    /** Kürzere Wege (z. B. Zonenwechsel im selben Gebäude) sind keine Fahrt. */
    public const MIN_DISTANCE_KM = 0.3;

    public function __construct(private readonly TrackDistanceSource $distances) {}

    public function buildAfter(LocationVisit $arrived): ?LocationPendingTrip {
        $previous = LocationVisit::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $arrived->organization_id)
            ->where('user_id', $arrived->user_id)
            ->where('status', LocationVisitStatus::Closed)
            ->whereKeyNot($arrived->id)
            ->whereNotNull('left_at')
            ->where('left_at', '<=', $arrived->entered_at)
            ->where('left_at', '>=', $arrived->entered_at->copy()->subHours(self::MAX_GAP_HOURS))
            ->orderByDesc('left_at')
            ->with('geofence')
            ->first();
        if (! $previous instanceof LocationVisit) {
            return null;
        }
        if (LocationPendingTrip::query()->withoutGlobalScope(OrganizationScope::class)->where('from_visit_id', $previous->id)->where('to_visit_id', $arrived->id)->exists()) {
            return null;
        }

        $points = array_values(LocationPoint::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $arrived->organization_id)
            ->where('user_id', $arrived->user_id)
            ->where('recorded_at', '>=', $previous->left_at)
            ->where('recorded_at', '<=', $arrived->entered_at)
            ->orderBy('recorded_at')
            ->get()
            ->map(static fn(LocationPoint $p): array => ['lat' => (float) $p->lat, 'lng' => (float) $p->lng])
            ->all());
        if (count($points) < 2) {
            return null;
        }

        $matched = $this->distances->distanceKm($points);
        $distance = $matched ?? round(GeoHelper::trackLengthKm($points, 'lat', 'lng'), 2);
        if ($distance < self::MIN_DISTANCE_KM) {
            return null;
        }
        $arrived->loadMissing('geofence');

        return LocationPendingTrip::create([
            'organization_id' => $arrived->organization_id,
            'user_id' => $arrived->user_id,
            'from_visit_id' => $previous->id,
            'to_visit_id' => $arrived->id,
            'from_label' => $previous->geofence?->label,
            'to_label' => $arrived->geofence?->label,
            'from_lat' => $points[0]['lat'],
            'from_lng' => $points[0]['lng'],
            'to_lat' => $points[count($points) - 1]['lat'],
            'to_lng' => $points[count($points) - 1]['lng'],
            'started_at' => $previous->left_at,
            'ended_at' => $arrived->entered_at,
            'distance_km' => $distance,
            'distance_source' => $matched !== null ? LocationPendingTrip::SOURCE_MATCH : LocationPendingTrip::SOURCE_TRACK,
            'point_count' => count($points),
            'status' => LocationPendingTripStatus::Open,
        ]);
    }
}
