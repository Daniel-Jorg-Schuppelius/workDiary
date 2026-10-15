<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TripMaterializer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location;

use App\Enums\Location\LocationPendingTripStatus;
use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Models\Location\LocationPendingTrip;
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Services\Routing\Contracts\TravelLogRecorder;
use App\Support\Tz;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fahrtvorschlag aus der Spur ins Fahrtenbuch übernehmen (MVP-1185, E3):
 * Fahrzeug, Fahrtart und Zweck gibt die Person an; Kette und Pflichtfelder
 * prüft das Fahrtenbuch selbst ({@see TravelLogRecorder}).
 */
class TripMaterializer {
    public function __construct(private readonly TravelLogRecorder $travelLogs) {}

    public function available(): bool {
        return $this->travelLogs->available();
    }

    /**
     * @param  array<string, mixed>  $input  vehicle, vehicle_id, trip_kind, purpose, odometer_start_km, odometer_end_km
     *
     * @throws \App\Exceptions\LogbookViolationException
     */
    public function confirm(LocationPendingTrip $trip, User $resolver, array $input): TravelLog {
        if (! $trip->status->canTransitionTo(LocationPendingTripStatus::Imported)) {
            throw new \RuntimeException('Fahrtvorschlag ist bereits aufgelöst.');
        }

        return DB::transaction(function () use ($trip, $resolver, $input): TravelLog {
            $log = $this->travelLogs->create([
                'organization_id' => $trip->organization_id,
                'user_id' => $trip->user_id,
                'vehicle_id' => $input['vehicle_id'] ?? null,
                'date' => Tz::toLocal($trip->started_at)?->toDateString(),
                'started_at' => $trip->started_at,
                'ended_at' => $trip->ended_at,
                'duration_minutes' => (int) $trip->started_at->diffInMinutes($trip->ended_at),
                'from_address' => $trip->from_label,
                'to_address' => $trip->to_label,
                'from_lat' => $trip->from_lat,
                'from_lng' => $trip->from_lng,
                'to_lat' => $trip->to_lat,
                'to_lng' => $trip->to_lng,
                'distance_km' => (float) $trip->distance_km,
                'odometer_start_km' => $input['odometer_start_km'] ?? null,
                'odometer_end_km' => $input['odometer_end_km'] ?? null,
                'trip_kind' => $input['trip_kind'] ?? TripKind::Business->value,
                'vehicle' => $input['vehicle'] ?? TravelLogVehicle::Company->value,
                'purpose' => $input['purpose'] ?? null,
                'round_trip' => false,
                'reimbursable' => true,
                // Kennzeichnung bleibt am Datensatz, nicht nur in der Anzeige: Spurlänge ist keine Straßenstrecke.
                'notes' => $trip->distance_source === LocationPendingTrip::SOURCE_TRACK ? 'GPS-Spur ohne Kartenabgleich' : null,
            ]);

            $trip->forceFill([
                'status' => LocationPendingTripStatus::Imported,
                'travel_log_id' => $log->id,
                'resolved_by' => $resolver->id,
                'resolved_at' => Carbon::now(),
            ])->save();

            return $log;
        });
    }

    public function dismiss(LocationPendingTrip $trip, User $resolver): void {
        if (! $trip->status->canTransitionTo(LocationPendingTripStatus::Dismissed)) {
            return;
        }

        $trip->forceFill([
            'status' => LocationPendingTripStatus::Dismissed,
            'resolved_by' => $resolver->id,
            'resolved_at' => Carbon::now(),
        ])->save();
    }
}
