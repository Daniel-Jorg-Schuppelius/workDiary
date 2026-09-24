<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Passenger\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class PassengerRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Fahrtakten (MVP-456, Konzept §11): abgeschlossene Fahrten werden
            // nach Frist anonymisiert — Orts-/Fahrgastfelder genullt (encrypted-
            // Regel: NULL, nie ""), Beträge/Steuer/Zeiten bleiben als Nachweis.
            new RetentionPolicy(
                area: 'passenger_rides',
                modelClass: \App\Models\Passenger\PassengerRide::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Passenger\PassengerRide::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', array_values(array_map(
                        static fn(\App\Enums\Passenger\RideStatus $status): string => $status->value,
                        array_filter(\App\Enums\Passenger\RideStatus::cases(), static fn(\App\Enums\Passenger\RideStatus $status): bool => $status->isFinal()),
                    )))
                    ->whereNull('anonymized_at')
                    ->where(fn($query) => $query
                        ->where('completed_at', '<', $cutoff)
                        ->orWhere('cancelled_at', '<', $cutoff)),
                purge: function (\App\Models\Passenger\PassengerRide $subject): void {
                    $subject->forceFill([
                        'pickup_address' => null,
                        'destination_address' => null,
                        'waypoints' => null,
                        'passenger_name' => null,
                        'passenger_contact' => null,
                        'route_note' => null,
                        'closing_note' => null,
                        'anonymized_at' => now(),
                    ])->save();
                    $subject->audit('passenger.ride_anonymized', ['reason' => 'retention']);
                },
            ),
        ];
    }
}
