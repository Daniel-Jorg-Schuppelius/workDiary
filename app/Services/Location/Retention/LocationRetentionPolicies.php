<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location\Retention;

use App\Enums\Location\{LocationPendingEntryStatus, LocationPendingTripStatus};
use App\Models\Location\{LocationPendingEntry, LocationPendingTrip};
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/**
 * Erledigte Vorschläge aus der Standortspur (Fahrten, Einträge) tragen Start-,
 * Zielkoordinaten und Zeiten je Person; nach der Frist der Rohspur gehen sie
 * mit (Sicherheitsaudit 2026-10-21, li-6). Offene Vorschläge bleiben.
 */
final class LocationRetentionPolicies implements RetentionPolicyProvider {
    /** @return list<RetentionPolicy> */
    public function policies(): array {
        return [
            new RetentionPolicy(
                area: 'location_trip_suggestions',
                modelClass: LocationPendingTrip::class,
                overdueQuery: fn ($organization, $cutoff) => LocationPendingTrip::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', [LocationPendingTripStatus::Imported->value, LocationPendingTripStatus::Dismissed->value])
                    ->where(static fn ($query) => $query->where('resolved_at', '<', $cutoff)
                        ->orWhere(static fn ($q) => $q->whereNull('resolved_at')->where('updated_at', '<', $cutoff))),
            ),
            new RetentionPolicy(
                area: 'location_time_suggestions',
                modelClass: LocationPendingEntry::class,
                overdueQuery: fn ($organization, $cutoff) => LocationPendingEntry::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', [LocationPendingEntryStatus::Imported->value, LocationPendingEntryStatus::Dismissed->value])
                    ->where(static fn ($query) => $query->where('resolved_at', '<', $cutoff)
                        ->orWhere(static fn ($q) => $q->whereNull('resolved_at')->where('updated_at', '<', $cutoff))),
            ),
        ];
    }
}
